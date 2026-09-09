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

use CmsIg\Seal\EngineInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Sulu\Product\Infrastructure\Sulu\Search\Schema\AttributeIndexFieldProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class CatalogueProductReindexAttributeEnhancerTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    public function testAttributeValuesMergeAcrossParentAndVariant(): void
    {
        self::purgeDatabase();

        // `note` is localized and `weight`/`colour` are not, so values land on both the localized
        // and the unlocalized dimension content row.
        $weightId = $this->createAttribute('weight', 'Weight', AttributeInterface::TYPE_NUMBER, false);
        $colourId = $this->createAttribute('colour', 'Colour', AttributeInterface::TYPE_OPTIONS, false, ['red' => 'Red', 'blue' => 'Blue']);
        $noteId = $this->createAttribute('note', 'Note', AttributeInterface::TYPE_TEXT, true);
        $this->clearAttributeIndexFields();

        $familyId = $this->createProductFamily([
            $weightId => ['enabled' => true],
            $colourId => ['enabled' => true, 'variantSpecific' => true],
            $noteId => ['enabled' => true],
        ]);
        $parentId = $this->createProduct($familyId, 'Cable', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->putAttributes($parentId, [$weightId => 2.5, $noteId => 'Gold plated']);
        $redId = $this->createVariant($parentId);
        $this->putVariantAttributes($parentId, $redId, [$colourId => 'red']);
        $blueId = $this->createVariant($parentId);
        $this->putVariantAttributes($parentId, $blueId, [$colourId => 'blue']);
        $this->publish($parentId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        // The parent shows its own values plus the variant-specific values of all its variants.
        $parent = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($parentId, 'en'));
        $this->assertSame([2.5], $parent['attr_weight']);
        $this->assertIsArray($parent['opt_colour']);
        $this->assertEqualsCanonicalizing(['red', 'blue'], $parent['opt_colour']);
        $this->assertIsArray($parent['content']);
        $this->assertContains('Gold plated', $parent['content']);
        $this->assertContains('Red', $parent['content']);
        $this->assertContains('Blue', $parent['content']);
        $this->assertIsArray($parent['attributes']);
        $this->assertIsArray($parent['attributes']['weight']);
        $this->assertSame('Weight', $parent['attributes']['weight']['label']);
        $this->assertSame(2.5, $parent['attributes']['weight']['value']);
        $this->assertIsArray($parent['attributes']['note']);
        $this->assertSame('Gold plated', $parent['attributes']['note']['value']);

        // A variant shows its own axis value plus the parent's shared values.
        $red = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($redId, 'en'));
        $this->assertSame([2.5], $red['attr_weight']);
        $this->assertSame(['red'], $red['opt_colour']);
        $this->assertIsArray($red['content']);
        $this->assertContains('Red', $red['content']);
        $this->assertNotContains('Blue', $red['content']);
        $this->assertContains('Gold plated', $red['content']);
        $this->assertIsArray($red['attributes']);
        $this->assertIsArray($red['attributes']['colour']);
        $this->assertSame('Red', $red['attributes']['colour']['value']);
        $this->assertSame('Colour', $red['attributes']['colour']['label']);

        $blue = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($blueId, 'en'));
        $this->assertSame(['blue'], $blue['opt_colour']);
    }

    public function testProductWithoutVariantsShowsOnlyItsOwnValues(): void
    {
        self::purgeDatabase();

        $weightId = $this->createAttribute('weight', 'Weight', AttributeInterface::TYPE_NUMBER, false);
        $noteId = $this->createAttribute('note', 'Note', AttributeInterface::TYPE_TEXT, true);
        $this->clearAttributeIndexFields();

        $familyId = $this->createProductFamily([
            $weightId => ['enabled' => true],
            $noteId => ['enabled' => true],
        ]);
        $firstId = $this->createProduct($familyId, 'First', ProductInterface::TYPE_PRODUCT);
        $this->putAttributes($firstId, [$weightId => 1.5, $noteId => 'First note']);
        $secondId = $this->createProduct($familyId, 'Second', ProductInterface::TYPE_PRODUCT);
        $this->putAttributes($secondId, [$weightId => 3.0]);
        $this->publish($firstId);
        $this->publish($secondId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $first = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($firstId, 'en'));
        $this->assertSame([1.5], $first['attr_weight']);
        $this->assertIsArray($first['content']);
        $this->assertContains('First note', $first['content']);

        $second = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($secondId, 'en'));
        $this->assertSame([3.0], $second['attr_weight']);
        $this->assertIsArray($second['attributes']);
        $this->assertArrayNotHasKey('note', $second['attributes']);
        $this->assertIsArray($second['content']);
        $this->assertNotContains('First note', $second['content']);
    }

    /**
     * The attribute fields are appended to the index schema from the attribute table, so the
     * attributes must exist and the cached field list must be dropped before the index is created.
     */
    private function clearAttributeIndexFields(): void
    {
        /** @var AttributeIndexFieldProvider $fieldProvider */
        $fieldProvider = self::getContainer()->get('sulu_product.attribute_index_field_provider');
        $fieldProvider->clear();
    }

    /**
     * @param array<string, string> $options option key => english name
     */
    private function createAttribute(string $key, string $name, string $type, bool $localized, array $options = []): int
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
        $attribute->setType($type);
        $attribute->setLocalized($localized);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));
        foreach ($options as $optionKey => $optionName) {
            $option = new AttributeOption($attribute, $optionKey);
            $option->addTranslation(new AttributeOptionTranslation($option, 'en', $optionName));
            $attribute->addOption($option);
        }
        $attributeRepository->save($attribute);
        $entityManager->flush();

        return $attribute->getId();
    }

    /**
     * @param array<int, array{enabled: bool, required?: bool, variantSpecific?: bool}> $attributes
     */
    private function createProductFamily(array $attributes = []): string
    {
        $normalized = [];
        foreach ($attributes as $attributeId => $entry) {
            $normalized[$attributeId] = [
                'enabled' => $entry['enabled'],
                'required' => $entry['required'] ?? false,
                'variantSpecific' => $entry['variantSpecific'] ?? false,
            ];
        }

        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode(\array_filter([
            'locale' => 'en',
            'name' => 'Test Family',
            'attributes' => $normalized ?: null,
        ], static fn ($value) => null !== $value)) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $familyId = $data['id'];
        $this->assertIsString($familyId);

        return $familyId;
    }

    private function createProduct(string $familyId, string $title, string $type): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => $title,
            'url' => '/attribute-product-' . $counter,
            'productFamily' => $familyId,
            'type' => $type,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    private function createVariant(string $parentId): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => 'ATTRIBUTE-VARIANT-' . $counter,
            'title' => 'Variant ' . $counter,
            'url' => '/attribute-variant-' . $counter,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param array<int, mixed> $attributes attribute id => value
     */
    private function putAttributes(string $id, array $attributes): void
    {
        $this->client->request('PUT', '/admin/api/products/' . $id . '.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'attributes' => $attributes,
        ]) ?: null);
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    /**
     * @param array<int, mixed> $attributes attribute id => value
     */
    private function putVariantAttributes(string $parentId, string $id, array $attributes): void
    {
        $this->client->request('PUT', '/admin/api/products/' . $parentId . '/variants/' . $id . '.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'attributes' => $attributes,
        ]) ?: null);
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    private function publish(string $id): void
    {
        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }
}
