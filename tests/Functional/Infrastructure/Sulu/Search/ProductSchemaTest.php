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
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Sulu\Product\Infrastructure\Sulu\Search\Schema\NumericAttributeLister;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

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
        foreach (['code', 'status', 'productFamilyId', 'productFamilyName', ProductIndex::TEXT_VALUES_FIELD, ProductIndex::NUMERIC_VALUES_FIELD, 'attributes'] as $name) {
            $this->assertArrayHasKey($name, $product->fields, $name);
        }

        $this->assertContains('product.code', $index->filterableFields);
        $this->assertContains('product.status', $index->facetFields);
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

        /** @var NumericAttributeLister $lister */
        $lister = $container->get('sulu_product.numeric_attribute_lister');
        $lister->clear();

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

    public function testNumericAttributeCacheIsInvalidatedAutomaticallyByTheDoctrineListener(): void
    {
        self::purgeDatabase();
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        /** @var NumericAttributeLister $lister */
        $lister = $container->get('sulu_product.numeric_attribute_lister');
        /** @var LoaderInterface $loader */
        $loader = $container->get('sulu_product.product_schema_loader');

        // Warm the cache with the database in its just-purged, attribute-free state. No test
        // below calls clear() itself; every field-list change must reach the schema through the
        // doctrine.orm.entity_listener wiring alone.
        $lister->clear();
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
        $this->assertInstanceOf(Field\FloatField::class, $fields['weight'], 'postPersist should have invalidated the cache');
        $this->assertTrue($fields['weight']->filterable);

        $attribute->setKey('mass');
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertArrayNotHasKey('weight', $fields, 'postUpdate should have invalidated the cache');
        $this->assertInstanceOf(Field\FloatField::class, $fields['mass']);

        $attributeRepository->remove($attribute);
        $entityManager->flush();

        $fields = $this->numericFields($loader->load()->indexes[ProductIndex::NAME]->fields);
        $this->assertArrayNotHasKey('mass', $fields, 'postRemove should have invalidated the cache');
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

    /**
     * The entity listener only invalidates the pool of the kernel context it runs in, so the entry
     * carries an expiry as well.
     */
    public function testCachedFieldListIsStoredWithAnExpiry(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $cache = new ArrayAdapter();
        (new NumericAttributeLister($entityManager, $cache))->getFields();

        $metadata = $cache->getItem(NumericAttributeLister::CACHE_KEY)->getMetadata();

        $this->assertArrayHasKey(CacheItem::METADATA_EXPIRY, $metadata);
        $this->assertEqualsWithDelta(
            \microtime(true) + NumericAttributeLister::CACHE_TTL,
            $metadata[CacheItem::METADATA_EXPIRY],
            30.0,
        );
    }
}
