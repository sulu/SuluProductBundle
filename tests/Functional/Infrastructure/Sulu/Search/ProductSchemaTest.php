<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Search;

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Sulu\Product\Infrastructure\Sulu\Search\Schema\ProductSchemaLoader;

class ProductSchemaTest extends SuluTestCase
{
    public function testWebsiteIndexCarriesTheProductFieldNextToSulusOwnFields(): void
    {
        /** @var Schema $schema */
        $schema = self::getContainer()->get('cmsig_seal.schema.default');

        $this->assertArrayHasKey(ProductIndex::NAME, $schema->indexes);
        $index = $schema->indexes[ProductIndex::NAME];

        // Sulu owns the index, so the fields a product document shares with a page come from it.
        foreach (['id', 'resourceKey', 'resourceId', 'locale', 'webspaces', 'title', 'url', 'content', 'mediaId', 'authoredAt', 'metadata'] as $name) {
            $this->assertArrayHasKey($name, $index->fields, $name);
        }

        $product = $index->fields[ProductIndex::FIELD];
        $this->assertInstanceOf(Field\ObjectField::class, $product);
        foreach (['code', 'productFamilyId', ProductIndex::TEXT_VALUES_FIELD, ProductIndex::NUMERIC_VALUES_FIELD] as $name) {
            $this->assertArrayHasKey($name, $product->fields, $name);
        }

        $this->assertContains('product.code', $index->filterableFields);
        $this->assertContains('product.productFamilyId', $index->facetFields);
        $this->assertContains(ProductIndex::textValuesPath(), $index->filterableFields);
        $this->assertContains(ProductIndex::textValuesPath(), $index->facetFields);
        $this->assertTrue($product->fields[ProductIndex::TEXT_VALUES_FIELD]->multiple);
    }

    public function testOnlyNumberAndDateAttributesBecomeIndexFields(): void
    {
        self::purgeDatabase();
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->create();
        $groupRepository->save($group);
        foreach ([['weight', AttributeInterface::TYPE_NUMBER], ['delivered', AttributeInterface::TYPE_DATE], ['colour', AttributeInterface::TYPE_OPTIONS], ['note', AttributeInterface::TYPE_TEXT]] as [$key, $type]) {
            $attribute = $attributeRepository->create($group);
            $attribute->setKey($key);
            $attribute->setType($type);
            $attributeRepository->save($attribute);
        }
        $entityManager->flush();

        /** @var LoaderInterface $loader */
        $loader = $container->get('sulu_product.product_schema_loader');
        $index = $loader->load()->indexes[ProductIndex::NAME];
        $fields = $this->numericFields($index->fields);

        $this->assertInstanceOf(Field\FloatField::class, $fields['weight']);
        $this->assertTrue($fields['weight']->filterable);
        $this->assertInstanceOf(Field\FloatField::class, $fields['delivered']);
        $this->assertArrayNotHasKey('colour', $fields, 'An options attribute is filtered through the text values.');
        $this->assertArrayNotHasKey('note', $fields);
        $this->assertContains(ProductIndex::numericValuePath('weight'), $index->filterableFields);
        $this->assertContains(ProductIndex::numericValuePath('weight'), $index->facetFields);
    }

    public function testSchemaFollowsTheAttributeTable(): void
    {
        self::purgeDatabase();
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        /** @var LoaderInterface $loader */
        $loader = $container->get('sulu_product.product_schema_loader');

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertArrayNotHasKey('weight', $fields);

        $group = $groupRepository->create();
        $groupRepository->save($group);
        $attribute = $attributeRepository->create($group);
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertInstanceOf(Field\FloatField::class, $fields['weight']);

        $attribute->setKey('mass');
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertArrayNotHasKey('weight', $fields);
        $this->assertInstanceOf(Field\FloatField::class, $fields['mass']);

        $attributeRepository->remove($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertArrayNotHasKey('mass', $fields);
    }

    public function testStaticFieldWinsOverNumericFieldOfSameName(): void
    {
        self::purgeDatabase();
        $this->createNumericAttribute('weight');

        $loader = $this->createLoader(new Schema([
            ProductIndex::NAME => new Index(ProductIndex::NAME, $this->websiteFields([
                'weight' => new Field\TextField('weight', searchable: false, filterable: true),
            ])),
        ]));

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertInstanceOf(Field\TextField::class, $fields['weight']);
    }

    public function testSchemaWithoutProductFieldIsReturnedUnchanged(): void
    {
        $original = new Schema([ProductIndex::NAME => new Index(ProductIndex::NAME, ['id' => new Field\IdentifierField('id')])]);

        $this->assertSame($original, $this->createLoader($original)->load());
    }

    private function createLoader(Schema $schema): ProductSchemaLoader
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $inner = new class($schema) implements LoaderInterface {
            public function __construct(private readonly Schema $schema)
            {
            }

            public function load(): Schema
            {
                return $this->schema;
            }
        };

        return new ProductSchemaLoader($inner, $entityManager);
    }

    private function createNumericAttribute(string $key): void
    {
        $container = self::getContainer();
        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->create();
        $groupRepository->save($group);
        $attribute = $attributeRepository->create($group);
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attributeRepository->save($attribute);
        $entityManager->flush();
    }

    /**
     * @param array<string, Field\AbstractField> $numericFields
     *
     * @return array<string, Field\AbstractField>
     */
    private function websiteFields(array $numericFields = []): array
    {
        return [
            'id' => new Field\IdentifierField('id'),
            ProductIndex::FIELD => new Field\ObjectField(ProductIndex::FIELD, [
                ProductIndex::TEXT_VALUES_FIELD => new Field\TextField(ProductIndex::TEXT_VALUES_FIELD, multiple: true, searchable: false, filterable: true, facet: true),
                ProductIndex::NUMERIC_VALUES_FIELD => new Field\ObjectField(ProductIndex::NUMERIC_VALUES_FIELD, $numericFields),
            ]),
        ];
    }

    /**
     * @param array<string, Field\AbstractField> $indexFields
     *
     * @return array<string, Field\AbstractField>
     */
    private function numericFields(array $indexFields): array
    {
        $product = $indexFields[ProductIndex::FIELD];
        $this->assertInstanceOf(Field\ObjectField::class, $product);
        $numericValues = $product->fields[ProductIndex::NUMERIC_VALUES_FIELD];
        $this->assertInstanceOf(Field\ObjectField::class, $numericValues);

        return $numericValues->fields;
    }
}
