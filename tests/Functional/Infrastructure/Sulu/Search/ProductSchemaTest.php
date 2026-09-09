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
use Sulu\Product\Infrastructure\Sulu\Search\Schema\AttributeIndexFieldProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

class ProductSchemaTest extends SuluTestCase
{
    public function testProductsIndexHasStaticFields(): void
    {
        /** @var Schema $schema */
        $schema = self::getContainer()->get('cmsig_seal.schema.default');

        $this->assertArrayHasKey(ProductIndex::NAME, $schema->indexes);
        $fields = $schema->indexes[ProductIndex::NAME]->fields;

        foreach (['id', 'resourceId', 'type', 'parentId', 'code', 'locale', 'webspaces', 'title', 'url', 'content', 'mediaId', 'productFamilyId', 'productFamilyName', 'status', 'authoredAt', 'changedAt', 'attributes', 'metadata'] as $name) {
            $this->assertArrayHasKey($name, $fields, $name);
        }

        $this->assertInstanceOf(Field\IdentifierField::class, $fields['id']);
        $this->assertTrue($fields['type']->filterable);
        $this->assertTrue($fields['parentId']->filterable);
        $this->assertTrue($fields['code']->searchable);
        $this->assertTrue($fields['code']->filterable);
        $this->assertTrue($fields['productFamilyId']->facet);
        $this->assertTrue($fields['status']->facet);
        $this->assertTrue($fields['content']->multiple);
    }

    public function testNumberAndOptionsAttributesBecomeIndexFields(): void
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
        foreach ([['weight', AttributeInterface::TYPE_NUMBER], ['colour', AttributeInterface::TYPE_OPTIONS], ['note', AttributeInterface::TYPE_TEXT]] as [$key, $type]) {
            $attribute = $attributeRepository->create($group);
            $attribute->setKey($key);
            $attribute->setType($type);
            $attributeRepository->save($attribute);
        }
        $entityManager->flush();

        /** @var AttributeIndexFieldProvider $fieldProvider */
        $fieldProvider = $container->get('sulu_product.attribute_index_field_provider');
        $fieldProvider->clear();

        /** @var LoaderInterface $loader */
        $loader = $container->get('sulu_product.product_schema_loader');
        $fields = $loader->load()->indexes[ProductIndex::NAME]->fields;

        $this->assertInstanceOf(Field\FloatField::class, $fields['attr_weight']);
        $this->assertTrue($fields['attr_weight']->filterable);
        $this->assertInstanceOf(Field\TextField::class, $fields['opt_colour']);
        $this->assertTrue($fields['opt_colour']->facet);
        $this->assertArrayNotHasKey('attr_note', $fields);
        $this->assertArrayNotHasKey('opt_note', $fields);
    }

    public function testAttributeIndexFieldCacheIsInvalidatedAutomaticallyByTheDoctrineListener(): void
    {
        self::purgeDatabase();
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        /** @var AttributeIndexFieldProvider $fieldProvider */
        $fieldProvider = $container->get('sulu_product.attribute_index_field_provider');
        /** @var LoaderInterface $loader */
        $loader = $container->get('sulu_product.product_schema_loader');

        // Warm the cache with the database in its just-purged, attribute-free state. No test
        // below calls clear() itself; every field-list change must reach the schema through the
        // doctrine.orm.entity_listener wiring alone.
        $fieldProvider->clear();
        $fields = $loader->load()->indexes[ProductIndex::NAME]->fields;
        $this->assertArrayNotHasKey('attr_weight', $fields);

        $group = $groupRepository->create();
        $groupRepository->save($group);
        $attribute = $attributeRepository->create($group);
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $loader->load()->indexes[ProductIndex::NAME]->fields;
        $this->assertInstanceOf(Field\FloatField::class, $fields['attr_weight'], 'postPersist should have invalidated the cache');
        $this->assertTrue($fields['attr_weight']->filterable);

        $attribute->setKey('mass');
        $attributeRepository->save($attribute);
        $entityManager->flush();

        $fields = $loader->load()->indexes[ProductIndex::NAME]->fields;
        $this->assertArrayNotHasKey('attr_weight', $fields, 'postUpdate should have invalidated the cache');
        $this->assertInstanceOf(Field\FloatField::class, $fields['attr_mass']);

        $attributeRepository->remove($attribute);
        $entityManager->flush();

        $fields = $loader->load()->indexes[ProductIndex::NAME]->fields;
        $this->assertArrayNotHasKey('attr_mass', $fields, 'postRemove should have invalidated the cache');
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
        (new AttributeIndexFieldProvider($entityManager, $cache))->getFields();

        $metadata = $cache->getItem(AttributeIndexFieldProvider::CACHE_KEY)->getMetadata();

        $this->assertArrayHasKey(CacheItem::METADATA_EXPIRY, $metadata);
        $this->assertEqualsWithDelta(
            \microtime(true) + AttributeIndexFieldProvider::CACHE_TTL,
            $metadata[CacheItem::METADATA_EXPIRY],
            30.0,
        );
    }
}
