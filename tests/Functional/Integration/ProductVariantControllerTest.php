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

namespace Sulu\Product\Tests\Functional\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductVariantController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductVariantController::class)]
class ProductVariantControllerTest extends SuluTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
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

        $this->client->request(
            'POST',
            '/admin/api/product-families.json?locale=en',
            [],
            [],
            [],
            \json_encode(\array_filter([
                'locale' => 'en',
                'name' => 'Test Family',
                'description' => null,
                'attributes' => $normalized ?: null,
            ], static fn ($v) => null !== $v)) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $familyId = $data['id'];
        $this->assertIsString($familyId);

        return $familyId;
    }

    private function createProduct(string $familyId, string $title = 'My Product', string $type = ProductInterface::TYPE_PRODUCT): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => $title,
                'url' => '/test-product-' . $counter,
                'productFamily' => $familyId,
                'type' => $type,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    private function createVariant(string $parentId, string $title = 'Variant'): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'VARIANT-' . $counter,
                'title' => $title,
                'url' => '/test-variant-' . $counter,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    private function createAttribute(string $key, string $name, string $type = AttributeInterface::TYPE_TEXT): int
    {
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->create();
        $groupRepository->save($group);

        $attribute = $attributeRepository->create($group);
        $attribute->setKey($key);
        $attribute->setType($type);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));
        $attributeRepository->save($attribute);

        $em->flush();

        return $attribute->getId();
    }

    /**
     * @return int[]
     */
    private function getPersistedAttributeIds(string $productId): array
    {
        $container = self::getContainer();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        /** @var ContentManagerInterface $contentManager */
        $contentManager = $container->get(ContentManagerInterface::class);

        $dimensionAttributes = [
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        $product = $productRepository->getOneBy(
            ['uuid' => $productId],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'dimensionAttributes' => $dimensionAttributes,
                ],
            ],
        );

        $dimensionContent = $contentManager->resolve($product, $dimensionAttributes);

        $ids = [];
        foreach ($dimensionContent->getAttributes() as $attributeValue) {
            $ids[] = $attributeValue->getAttribute()->getId();
        }

        return $ids;
    }

    public function testPostCreatesVariantUnderParentAndIsExcludedFromMainList(): void
    {
        self::purgeDatabase();

        $axisId = $this->createAttribute('size', 'Size');
        $familyId = $this->createProductFamily([$axisId => ['enabled' => true, 'variantSpecific' => true]]);
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'L'],
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $childId = $data['id'];
        $this->assertIsString($childId);

        // The variant is a real child Product with `parent` set.
        $container = self::getContainer();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $variant = $productRepository->getOneBy(['uuid' => $childId]);
        $this->assertInstanceOf(Product::class, $variant);
        $this->assertNotNull($variant->getParent());
        $this->assertSame($parentId, $variant->getParent()->getUuid());

        // ... and must never show up in the main product list.
        $this->client->request('GET', '/admin/api/products.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $listData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($listData);
        $this->assertIsArray($listData['_embedded']);
        $this->assertIsArray($listData['_embedded']['products']);
        $ids = \array_column($listData['_embedded']['products'], 'id');
        $this->assertContains($parentId, $ids);
        $this->assertNotContains($childId, $ids);
    }

    public function testPostRejectsVariantUnderNonVariantParent(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Plain Product', ProductInterface::TYPE_PRODUCT);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'PLAIN-1',
                'title' => 'Should Fail',
            ]) ?: null,
        );

        $this->assertHttpStatusCode(409, $this->client->getResponse());
    }

    public function testGetOnParentProductReturnsPersistedType(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $variantParentId = $this->createProduct($familyId, 'Variant Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $simpleId = $this->createProduct($familyId, 'Simple Product', ProductInterface::TYPE_PRODUCT);

        $this->client->request('GET', '/admin/api/products/' . $variantParentId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $variantData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($variantData);
        $this->assertArrayHasKey('type', $variantData);
        $this->assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $variantData['type']);

        $this->client->request('GET', '/admin/api/products/' . $simpleId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $simpleData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($simpleData);
        $this->assertArrayHasKey('type', $simpleData);
        $this->assertSame(ProductInterface::TYPE_PRODUCT, $simpleData['type']);
    }

    public function testGetNotFound(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId);

        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/non-existent-uuid.json?locale=en');
        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testGetWithMismatchedParentReturns404(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentA = $this->createProduct($familyId, 'Parent A');
        $parentB = $this->createProduct($familyId, 'Parent B', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantOfB = $this->createVariant($parentB, 'Variant of B');

        $this->client->request('GET', '/admin/api/products/' . $parentA . '/variants/' . $variantOfB . '.json?locale=en');
        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPutWithMismatchedParentReturns404AndDoesNotReparent(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentA = $this->createProduct($familyId, 'Parent A');
        $parentB = $this->createProduct($familyId, 'Parent B', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantOfB = $this->createVariant($parentB, 'Variant of B');

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentA . '/variants/' . $variantOfB . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'VARIANT-HIJACK',
                'title' => 'Hijacked Variant',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(404, $this->client->getResponse());

        // Data-integrity guard: the variant must still be parented to parentB (no silent re-parenting).
        $container = self::getContainer();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $variant = $productRepository->getOneBy(['uuid' => $variantOfB]);
        $this->assertInstanceOf(Product::class, $variant);
        $this->assertNotNull($variant->getParent());
        $this->assertSame($parentB, $variant->getParent()->getUuid());
    }

    public function testDeleteWithMismatchedParentReturns404AndDoesNotDelete(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentA = $this->createProduct($familyId, 'Parent A');
        $parentB = $this->createProduct($familyId, 'Parent B', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantOfB = $this->createVariant($parentB, 'Variant of B');

        $this->client->request('DELETE', '/admin/api/products/' . $parentA . '/variants/' . $variantOfB . '.json?locale=en');
        $this->assertHttpStatusCode(404, $this->client->getResponse());

        // The variant must still exist, still parented to parentB.
        $this->client->request('GET', '/admin/api/products/' . $parentB . '/variants/' . $variantOfB . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    public function testInheritedAttributesAreStrippedOnWriteAndNeverReturnedOnVariantRead(): void
    {
        self::purgeDatabase();

        $sharedId = $this->createAttribute('color', 'Color');
        $axisId = $this->createAttribute('size', 'Size');
        $familyId = $this->createProductFamily([
            $sharedId => ['enabled' => true, 'variantSpecific' => false],
            $axisId => ['enabled' => true, 'variantSpecific' => true],
        ]);
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        // The parent carries the shared (non-variant) attribute value.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Parent Product',
                'attributes' => [$sharedId => 'Red'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // The variant is created with only its own axis value; a shared value submitted
        // alongside it (e.g. via the raw API) must never be persisted on the variant.
        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'L', $sharedId => 'Green'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];
        $this->assertIsString($childId);

        // The inherited attribute submitted on create must not be persisted on the variant.
        $this->assertSame([$axisId], $this->getPersistedAttributeIds($childId));

        // The shared attribute is never merged in from the parent for display.
        $this->assertIsArray($created['attributes']);
        $this->assertSame('L', $created['attributes'][$axisId]);
        $this->assertNull($created['attributes'][$sharedId]);

        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $fetched = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($fetched);
        $this->assertIsArray($fetched['attributes']);
        $this->assertSame('L', $fetched['attributes'][$axisId]);
        $this->assertNull($fetched['attributes'][$sharedId]);

        // PUT attempting to submit the shared attribute must also be stripped.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'XL', $sharedId => 'Blue'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $updated = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($updated);

        $this->assertSame([$axisId], $this->getPersistedAttributeIds($childId));

        $this->assertIsArray($updated['attributes']);
        $this->assertSame('XL', $updated['attributes'][$axisId]);
        $this->assertNull($updated['attributes'][$sharedId]);

        // The parent's own value is untouched by anything submitted on the variant.
        $this->client->request('GET', '/admin/api/products/' . $parentId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $parentFetched = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($parentFetched);
        $this->assertIsArray($parentFetched['attributes']);
        $this->assertSame('Red', $parentFetched['attributes'][$sharedId]);
    }

    public function testCgetListsVariantsOfParent(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $otherParentId = $this->createProduct($familyId, 'Other Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'code' => 'CX3-RD-L', 'title' => 'Variant L']) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];

        $this->client->request(
            'POST',
            '/admin/api/products/' . $otherParentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'code' => 'CX3-BL-L', 'title' => 'Other Variant']) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $list = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($list);
        $this->assertIsArray($list['_embedded']);
        $this->assertIsArray($list['_embedded']['product_variants']);
        $ids = \array_column($list['_embedded']['product_variants'], 'id');
        $this->assertContains($childId, $ids);
        $this->assertCount(1, $ids);

        // The rewritten cget goes through the Doctrine ListBuilder / PaginatedRepresentation,
        // so the payload carries pagination metadata (not just a bare `_embedded` collection).
        $this->assertSame(1, $list['total']);
        $this->assertSame(1, $list['page']);
        $this->assertIsInt($list['limit']);
        $this->assertArrayHasKey('pages', $list);

        $row = $list['_embedded']['product_variants'][0];
        $this->assertIsArray($row);
        $this->assertSame('CX3-RD-L', $row['code']);
        $this->assertArrayHasKey('status', $row);
        $this->assertSame('Variant L', $row['name']);
    }

    public function testDelete(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'code' => 'CX3-RD-L', 'title' => 'Variant L']) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];
        $this->assertIsString($childId);

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en');
        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testRestoringADeletedVariantKeepsItsParent(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        // Restoring is normally a separate HTTP request with a fresh EntityManager; reboot the
        // kernel to avoid a stale identity map from the DELETE flush breaking the restore's flush
        // (same trick as sulu/sulu's own TagTrashItemHandlerTest).
        self::ensureKernelShutdown();
        self::bootKernel();

        $container = self::getContainer();
        /** @var TrashItemRepositoryInterface $trashItemRepository */
        $trashItemRepository = $container->get(TrashItemRepositoryInterface::class);
        $trashItem = $trashItemRepository->getOneBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $variantId,
        ]);

        /** @var TrashManagerInterface $trashManager */
        $trashManager = $container->get(TrashManagerInterface::class);
        $trashManager->restore($trashItem);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager->flush();

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $restored = $productRepository->getOneBy(['uuid' => $variantId]);
        $this->assertInstanceOf(Product::class, $restored);
        $this->assertNotNull($restored->getParent());
        $this->assertSame($parentId, $restored->getParent()->getUuid());
    }

    /**
     * `type` is identity-owned (not on the dimension content) — trash restore must round-trip it
     * or a restored variant-parent silently loses its Variants tab.
     */
    public function testRestoringADeletedVariantParentKeepsItsType(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Variant Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        // Same identity-map reason as testRestoringADeletedVariantKeepsItsParent. Recreate the
        // client instead of calling bootKernel() directly — createAuthenticatedClient() boots
        // its own kernel and a second explicit boot is rejected by WebTestCase::createClient().
        self::ensureKernelShutdown();
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );

        $container = self::getContainer();
        /** @var TrashItemRepositoryInterface $trashItemRepository */
        $trashItemRepository = $container->get(TrashItemRepositoryInterface::class);
        $trashItem = $trashItemRepository->getOneBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $parentId,
        ]);

        /** @var TrashManagerInterface $trashManager */
        $trashManager = $container->get(TrashManagerInterface::class);
        $trashManager->restore($trashItem);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager->flush();

        // Assert through the admin API — never through $product->getType() directly.
        $this->client->request('GET', '/admin/api/products/' . $parentId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('type', $data);
        $this->assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $data['type']);
    }

    public function testDeletingParentTrashesEachVariant(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        // Same identity-map reason as testRestoringADeletedVariantKeepsItsParent.
        self::ensureKernelShutdown();
        self::bootKernel();

        $container = self::getContainer();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);

        // The variant is gone from the DB (cascade-deleted alongside the parent) …
        $this->assertNull($productRepository->findOneBy(['uuid' => $variantId]));

        // … but a restorable trash item exists for it.
        /** @var TrashItemRepositoryInterface $trashItemRepository */
        $trashItemRepository = $container->get(TrashItemRepositoryInterface::class);
        $trashItem = $trashItemRepository->findOneBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $variantId,
        ]);
        $this->assertNotNull($trashItem);

        // The parent was cascade-deleted too, so restore it first — otherwise the variant's
        // `parent` re-attach lookup finds no row and is silently left null
        // (ProductTrashItemHandler::restore()'s documented no-op case).
        /** @var TrashItemRepositoryInterface $trashItemRepository */
        $parentTrashItem = $trashItemRepository->findOneBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $parentId,
        ]);
        $this->assertNotNull($parentTrashItem);

        /** @var TrashManagerInterface $trashManager */
        $trashManager = $container->get(TrashManagerInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Flush after restoring the parent so the variant's re-attach lookup (a DB query) finds it.
        $trashManager->restore($parentTrashItem);
        $entityManager->flush();

        $trashManager->restore($trashItem);
        $entityManager->flush();

        $restored = $productRepository->getOneBy(['uuid' => $variantId]);
        $this->assertInstanceOf(Product::class, $restored);
        $this->assertNotNull($restored->getParent());
        $this->assertSame($parentId, $restored->getParent()->getUuid());
    }

    public function testWorkflowTriggerRouteIsGoneForVariants(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en&action=publish',
        );

        // POST is no longer a defined method on this path (only GET/PUT/DELETE remain).
        $this->assertHttpStatusCode(405, $this->client->getResponse());
    }

    public function testPutDoesNotPublishAVariant(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        // Even with a publish action query param, PUT only saves the draft — never publishes.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en&action=publish',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $updated = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($updated);

        // The variant has no live dimension content (still draft-only) — `publishedState`
        // (from Sulu's WorkflowNormalizer) is only ever true once the workflow's
        // `publish` transition has actually run.
        $this->assertFalse($updated['publishedState']);
    }

    public function testVariantCreateAndModifyDoNotRequireInheritedNonVariantAttribute(): void
    {
        self::purgeDatabase();

        $sharedId = $this->createAttribute('color', 'Color');
        $axisId = $this->createAttribute('size', 'Size');
        $familyId = $this->createProductFamily([
            $sharedId => ['enabled' => true, 'required' => true, 'variantSpecific' => false],
            $axisId => ['enabled' => true, 'variantSpecific' => true],
        ]);
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        // The parent carries the required shared (non-variant) attribute value.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Parent Product',
                'attributes' => [$sharedId => 'Red'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // Creating a variant with only its own axis value must succeed, even
        // though the family has a required non-variant attribute the variant
        // itself never carries (it is inherited from the parent).
        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'L'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];
        $this->assertIsString($childId);

        // Modifying the variant, again with only its own axis value, must
        // also succeed.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'XL'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    public function testSimpleProductStillRequiresRequiredNonVariantAttribute(): void
    {
        self::purgeDatabase();

        $sharedId = $this->createAttribute('color', 'Color');
        $familyId = $this->createProductFamily([
            $sharedId => ['enabled' => true, 'required' => true, 'variantSpecific' => false],
        ]);
        $parentId = $this->createProduct($familyId, 'Simple Product');

        // A non-variant (parent/simple) product must still be rejected when the
        // required attribute is missing; the variant exemption must not weaken
        // this check.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Simple Product',
                'attributes' => [$sharedId => null],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(422, $this->client->getResponse());
    }

    public function testParentWithRequiredVariantAttributeIsSavable(): void
    {
        self::purgeDatabase();

        // A required variant axis is only ever rendered on the variant overlay, so the parent
        // has no field to satisfy it with — enforcing it there made the parent unsavable.
        $axisId = $this->createAttribute('size', 'Size');
        $familyId = $this->createProductFamily([
            $axisId => ['enabled' => true, 'required' => true, 'variantSpecific' => true],
        ]);
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Parent Product',
                'attributes' => [],
            ]) ?: null,
        );

        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    public function testVariantPersistsDetailsFields(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'details' => ['shortDescription' => '<p>Variant blurb</p>'],
            ]) ?: null,
        );

        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request(
            'GET',
            '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en',
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['details']);
        $this->assertSame('<p>Variant blurb</p>', $data['details']['shortDescription']);
    }

    public function testCreatingAVariantMarksParentAsHavingUnpublishedChanges(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->createVariant($parentId, 'Variant L');

        $this->client->request('GET', '/admin/api/products/' . $parentId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $parent = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($parent);
        self::assertFalse($parent['publishedState']); // published, but now has unpublished changes
    }

    public function testClientSubmittedTypeIsIgnored(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'type' => 'bogus-type',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];
        $this->assertIsString($childId);

        $container = self::getContainer();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $variant = $productRepository->getOneBy(['uuid' => $childId]);
        $this->assertInstanceOf(Product::class, $variant);
        $this->assertSame(ProductInterface::TYPE_VARIANT, $variant->getType());

        // ... and a PUT attempting the same must also be ignored.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'type' => 'bogus-type',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $variant = $productRepository->getOneBy(['uuid' => $childId]);
        $this->assertInstanceOf(Product::class, $variant);
        $this->assertSame(ProductInterface::TYPE_VARIANT, $variant->getType());
    }

    public function testPublishingParentPublishesItsVariants(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        // Before publish: variant is draft-only.
        $this->assertFalse($this->getVariantPublishedState($parentId, $variantId));

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // After parent publish: the variant now has a live (published) dimension content.
        $this->assertTrue($this->getVariantPublishedState($parentId, $variantId));
    }

    /**
     * A variant untranslated into the locale being published is a normal state; the cascade must
     * skip it without failing the parent's own publish or its translated siblings' cascade.
     */
    public function testPublishingParentInASecondLocaleSkipsAVariantWithNoContentInThatLocale(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        // Give the parent a second locale.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '.json?locale=de',
            [],
            [],
            [],
            \json_encode(['locale' => 'de', 'title' => 'Elternprodukt', 'url' => '/de-parent-product']) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // A variant that only ever exists in 'en' — a normal, unremarkable state.
        $variantEnOnlyId = $this->createVariant($parentId, 'Variant EN Only');

        // A second variant that IS translated into 'de'.
        $variantTranslatedId = $this->createVariant($parentId, 'Variant Translated');
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $variantTranslatedId . '.json?locale=de',
            [],
            [],
            [],
            \json_encode(['locale' => 'de', 'title' => 'Variante DE', 'url' => '/de-variant-translated']) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // Must succeed even though $variantEnOnlyId has no 'de' content at all.
        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=de&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // The parent itself published successfully in 'de'.
        $this->client->request('GET', '/admin/api/products/' . $parentId . '.json?locale=de');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $parentData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($parentData);
        $this->assertTrue($parentData['publishedState']);

        // The other, translated variant's cascade still went through — it is live in 'de'.
        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/' . $variantTranslatedId . '.json?locale=de');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $translatedData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($translatedData);
        $this->assertTrue($translatedData['publishedState']);

        // Skipped by the cascade; the GET below still works via Sulu's ghost-locale fallback to
        // 'en' — a fallback ContentWorkflow::apply() doesn't have, hence the cascade's skip.
        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/' . $variantEnOnlyId . '.json?locale=de');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $enOnlyData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($enOnlyData);
        $this->assertSame('en', $enOnlyData['ghostLocale']);
        $this->assertFalse($enOnlyData['publishedState']);
    }

    public function testUnpublishingParentUnpublishesItsVariants(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $this->assertTrue($this->getVariantPublishedState($parentId, $variantId));

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=unpublish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $this->assertFalse($this->getVariantPublishedState($parentId, $variantId));
    }

    private function getVariantPublishedState(string $parentId, string $variantId): bool
    {
        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsBool($data['publishedState']);

        return $data['publishedState'];
    }

    /**
     * A plain locale mismatch (GET with a locale the variant was never translated into) does not
     * reach this branch — Sulu's ghost-locale fallback still resolves content via the always-present
     * unlocalized dimension content row (see testPublishingParentInASecondLocaleSkipsAVariantWithNoContentInThatLocale).
     * ContentNotFoundException is only raised once every dimension content row is gone, so — mirroring
     * ProductControllerTest::testGetReturnsTemplateOnlyWhenContentMissing() — the rows are deleted directly.
     */
    public function testGetReturnsTemplateOnlyWhenVariantContentMissing(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId, 'Variant L');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->createQuery('DELETE FROM ' . ProductDimensionContent::class . ' pdc WHERE pdc.product = (SELECT p FROM ' . Product::class . ' p WHERE p.uuid = :uuid)')
            ->setParameter('uuid', $variantId)
            ->execute();
        $em->clear();

        $this->client->request('GET', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
        $this->assertSame(
            ['template' => ProductInterface::TEMPLATE_TYPE],
            \json_decode((string) $response->getContent(), true),
        );
    }

    public function testPostWithNonExistentParentReturns404(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/products/non-existent-uuid/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'code' => 'X-1', 'title' => 'Should Fail']) ?: null,
        );

        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPutWithNonExistentVariantIdReturns404(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/non-existent-uuid.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'code' => 'X-1', 'title' => 'Should Fail']) ?: null,
        );

        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testDeleteWithNonExistentVariantIdReturns404(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '/variants/non-existent-uuid.json?locale=en');

        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPutClearingRequiredVariantAttributeReturns422(): void
    {
        self::purgeDatabase();

        $axisId = $this->createAttribute('size', 'Size');
        $familyId = $this->createProductFamily([$axisId => ['enabled' => true, 'required' => true, 'variantSpecific' => true]]);
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'L'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];
        $this->assertIsString($childId);

        // Explicitly clearing the required axis attribute's value must be rejected.
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => null],
            ]) ?: null,
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(422, $response);
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPutWithInvalidAttributeValueReturns400(): void
    {
        self::purgeDatabase();

        $axisId = $this->createAttribute('weight', 'Weight', AttributeInterface::TYPE_NUMBER);
        $familyId = $this->createProductFamily([$axisId => ['enabled' => true, 'variantSpecific' => true]]);
        $parentId = $this->createProduct($familyId, 'Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => '10'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $childId = $created['id'];
        $this->assertIsString($childId);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/variants/' . $childId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'CX3-RD-L',
                'title' => 'Variant L',
                'attributes' => [$axisId => 'not-a-number'],
            ]) ?: null,
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(400, $response);
        $this->assertSame(
            ['detail' => 'Invalid attribute value provided.'],
            \json_decode((string) $response->getContent(), true),
        );
    }

    /**
     * `resolveFamily()` throws a plain `\RuntimeException` — surfaced as a 500 — when the parent
     * was never assigned a product family (`productFamily` omitted at creation).
     */
    public function testPostVariantUnderParentWithNoProductFamilyReturns500(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Orphan Parent',
                'url' => '/orphan-parent',
                'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $parentId = $data['id'];
        $this->assertIsString($parentId);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'code' => 'ORPHAN-1', 'title' => 'Should Fail']) ?: null,
        );

        $this->assertHttpStatusCode(500, $this->client->getResponse());
    }
}
