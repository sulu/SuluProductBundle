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
use Sulu\Product\Infrastructure\Sulu\Search\Schema\ProductSchemaLoader;

class ProductSchemaTest extends SuluTestCase
{
    public function testWebsiteIndexCarriesTheProductFieldNextToSulusOwnFields(): void
    {
        /** @var Schema $schema */
        $schema = self::getContainer()->get('cmsig_seal.schema.default');

        $this->assertArrayHasKey('website', $schema->indexes);
        $index = $schema->indexes['website'];

        // Sulu owns the index, so the fields a product document shares with a page come from it.
        foreach (['id', 'resourceKey', 'resourceId', 'locale', 'webspaces', 'title', 'url', 'content', 'mediaId', 'authoredAt', 'metadata'] as $name) {
            $this->assertArrayHasKey($name, $index->fields, $name);
        }

        $product = $index->fields['product'];
        $this->assertInstanceOf(Field\ObjectField::class, $product);
        foreach (['productFamilyId', 'attributes_text_values', 'attributes_numeric_values'] as $name) {
            $this->assertArrayHasKey($name, $product->fields, $name);
        }

        $this->assertContains('product.productFamilyId', $index->facetFields);
        $this->assertContains('product.attributes_text_values', $index->filterableFields);
        $this->assertContains('product.attributes_text_values', $index->facetFields);
        $this->assertTrue($product->fields['attributes_text_values']->multiple);
    }

    public function testOnlyFilterableNumberAndDateAttributesBecomeIndexFields(): void
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
        foreach ([
            ['weight', AttributeInterface::TYPE_NUMBER, true],
            ['delivered', AttributeInterface::TYPE_DATE, true],
            ['length', AttributeInterface::TYPE_NUMBER, false],
            ['colour', AttributeInterface::TYPE_OPTIONS, true],
            ['note', AttributeInterface::TYPE_TEXT, false],
        ] as [$key, $type, $filterable]) {
            $attribute = $attributeRepository->create($group);
            $attribute->setKey($key);
            $attribute->setType($type);
            $attribute->setFilterable($filterable);
            $attributeRepository->save($attribute);
        }
        $entityManager->flush();

        /** @var LoaderInterface $loader */
        $loader = $container->get('sulu_product.product_schema_loader');
        $index = $loader->load()->indexes['website'];
        $fields = $this->numericFields($index->fields);

        $this->assertInstanceOf(Field\FloatField::class, $fields['weight']);
        $this->assertTrue($fields['weight']->filterable);
        $this->assertInstanceOf(Field\FloatField::class, $fields['delivered']);
        $this->assertArrayNotHasKey('length', $fields, 'A number attribute with Filterable off gets no field.');
        $this->assertArrayNotHasKey('colour', $fields, 'An options attribute is filtered through the text values.');
        $this->assertArrayNotHasKey('note', $fields);
        $this->assertContains('product.attributes_numeric_values.weight', $index->filterableFields);
        $this->assertContains('product.attributes_numeric_values.weight', $index->facetFields);
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

        $fields = $this->numericFields($loader->load()->indexes['website']->fields);
        $this->assertArrayNotHasKey('weight', $fields);

        $group = $groupRepository->create();
        $groupRepository->save($group);
        $attribute = $attributeRepository->create($group);
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setFilterable(true);
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes['website']->fields);
        $this->assertInstanceOf(Field\FloatField::class, $fields['weight']);

        $attribute->setFilterable(false);
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $this->assertArrayNotHasKey('weight', $this->numericFields($loader->load()->indexes['website']->fields));

        $attribute->setFilterable(true);

        $attribute->setKey('mass');
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes['website']->fields);
        $this->assertArrayNotHasKey('weight', $fields);
        $this->assertInstanceOf(Field\FloatField::class, $fields['mass']);

        $attributeRepository->remove($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes['website']->fields);
        $this->assertArrayNotHasKey('mass', $fields);
    }

    public function testKeyStartingWithADigitIsPrefixed(): void
    {
        self::purgeDatabase();
        $this->createNumericAttribute('1st_size');

        /** @var LoaderInterface $loader */
        $loader = self::getContainer()->get('sulu_product.product_schema_loader');

        $this->assertSame(['a_1st_size'], \array_keys($this->numericFields($loader->load()->indexes['website']->fields)));
    }

    public function testProductFieldIsAddedToTheWebsiteIndex(): void
    {
        self::purgeDatabase();

        $original = new Schema(['website' => new Index('website', ['id' => new Field\IdentifierField('id')])]);
        $index = $this->createLoader($original)->load()->indexes['website'];

        $this->assertArrayHasKey('id', $index->fields);
        $this->assertSame([], $this->numericFields($index->fields));
        $this->assertContains('product.productFamilyId', $index->filterableFields);
    }

    public function testSchemaWithoutWebsiteIndexIsReturnedUnchanged(): void
    {
        $original = new Schema(['admin' => new Index('admin', ['id' => new Field\IdentifierField('id')])]);

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
        $attribute->setFilterable(true);
        $attributeRepository->save($attribute);
        $entityManager->flush();
    }

    /**
     * @param array<string, Field\AbstractField> $indexFields
     *
     * @return array<string, Field\AbstractField>
     */
    private function numericFields(array $indexFields): array
    {
        $product = $indexFields['product'];
        $this->assertInstanceOf(Field\ObjectField::class, $product);
        $numericValues = $product->fields['attributes_numeric_values'];
        $this->assertInstanceOf(Field\ObjectField::class, $numericValues);

        return $numericValues->fields;
    }
}
