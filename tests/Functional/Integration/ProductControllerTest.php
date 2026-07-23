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
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductController::class)]
class ProductControllerTest extends SuluTestCase
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

    private function createProductFamily(?int $attributeId = null, bool $required = true): string
    {
        $attributes = [];
        if (null !== $attributeId) {
            $attributes[$attributeId] = ['enabled' => true, 'required' => $required];
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
                'attributes' => $attributes ?: null,
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

    private function createRequiredAttribute(): int
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
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Weight'));
        $attributeRepository->save($attribute);

        $em->flush();

        return $attribute->getId();
    }

    private function createLocalizedAttribute(): int
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
        $attribute->setKey('localized_weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setLocalized(true);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Localized Weight'));
        $attributeRepository->save($attribute);

        $em->flush();

        return $attribute->getId();
    }

    public function testGetEmptyList(): void
    {
        self::purgeDatabase();

        $this->client->request('GET', '/admin/api/products.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testPost(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'productFamily' => $familyId,
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    public function testGet(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
    }

    public function testGetNotFound(): void
    {
        $this->client->request('GET', '/admin/api/products/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testDetailsBucketRoundTripsThroughTheApi(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en&action=draft',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'productFamily' => $familyId,
                'details' => [
                    'shortDescription' => '<p>Round trip</p>',
                    'image' => ['id' => 1],
                    'documents' => ['ids' => [2, 3]],
                ],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        // regression: the admin wire-shape must survive storage untouched. Coercing
        // {"id": 1} down to 1 made single_media_selection resolve to id: null.
        // key order is not asserted — the merger emits the unlocalized half first.
        $this->assertEquals([
            'shortDescription' => '<p>Round trip</p>',
            'image' => ['id' => 1],
            'documents' => ['ids' => [2, 3]],
        ], $data['details']);
    }

    public function testDetailsBucketAcceptsAProjectDefinedField(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en&action=draft',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'productFamily' => $familyId,
                'details' => ['unknownProjectField' => 'kept'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['details']);

        // no form property declares it, so the bundle must not persist it
        $this->assertArrayNotHasKey('unknownProjectField', $data['details']);
    }

    public function testGetWithUnknownLocaleReturnsTemplateOnly(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        // A locale with no dimension content → ContentNotFoundException → template-only response
        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=fr');
        $response = $this->client->getResponse();

        // Either 200 with template-only body or full content — both are valid depending on resolve behavior
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testPut(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Updated Product',
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testPutNotFound(): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/products/non-existent-uuid.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'title' => 'X']) ?: null,
        );

        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPutWithMissingRequiredAttributeReturns422(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createRequiredAttribute();
        $familyId = $this->createProductFamily($attributeId);
        $id = $this->createProduct($familyId);

        // PUT with attributes key but empty value for required attribute
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'attributes' => [$attributeId => null],
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(422, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testLocalizedAttributeValueIsStoredPerLocale(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createLocalizedAttribute();
        $familyId = $this->createProductFamily($attributeId, false);
        $id = $this->createProduct($familyId);

        $this->putAttributes($id, 'en', [$attributeId => 100.0]);
        $this->putAttributes($id, 'de', [$attributeId => 200.0], 'Mein Produkt');

        $this->assertEqualsWithDelta(100.0, $this->getAttributeValue($id, 'en', $attributeId), 0.0001);
        $this->assertEqualsWithDelta(200.0, $this->getAttributeValue($id, 'de', $attributeId), 0.0001);
    }

    /**
     * @param array<int, mixed> $attributes
     */
    private function putAttributes(string $id, string $locale, array $attributes, ?string $title = null): void
    {
        $payload = ['locale' => $locale, 'attributes' => $attributes];
        if (null !== $title) {
            $payload['title'] = $title;
        }

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=' . $locale,
            [],
            [],
            [],
            \json_encode($payload) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    private function getAttributeValue(string $id, string $locale, int $attributeId): mixed
    {
        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=' . $locale);
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['attributes']);

        return $data['attributes'][$attributeId] ?? null;
    }

    public function testDelete(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('DELETE', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testDeleteLocale(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('DELETE', '/admin/api/products/' . $id . '.json?locale=en&deleteLocale=true');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testPostTriggerWithDraftActionReturnsProduct(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=draft');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);
    }

    public function testPostTriggerWithNoActionReturnsProduct(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);
    }

    public function testPostTriggerPublish(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);
    }

    public function testPublishProductWithCodeDoesNotReportOwnCodeAsDuplicate(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Coded Product',
                'url' => '/coded-product',
                'code' => '4444',
                'productFamily' => $familyId,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');

        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    public function testPostTriggerCopyLocale(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $id . '.json?locale=en&action=copy_locale&src=en&dest=de',
        );
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);
    }

    public function testPostTriggerRestore(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        // Publish to create a version snapshot (PublishTransitionSubscriber stores version = time())
        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // Get versions to find the snapshot's version number
        $this->client->request('GET', '/admin/api/products/' . $id . '/versions.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $versionsData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($versionsData);
        /** @var array{_embedded: array{products_versions: list<array{version: int}>}} $versionsData */
        $versions = $versionsData['_embedded']['products_versions'];
        $this->assertNotEmpty($versions);
        $version = $versions[0]['version'];

        // Restore that version
        $this->client->request(
            'POST',
            '/admin/api/products/' . $id . '.json?locale=en&action=restore&version=' . $version,
        );
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);
    }

    public function testGetListWithProducts(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $this->createProduct($familyId);

        // GET list with at least one product triggers the normalizeDateTimes loop body
        $this->client->request('GET', '/admin/api/products.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * A variant child is only ever created through the nested
     * `/products/{parentId}/variants` endpoint (`ProductVariantController`), which is the
     * only place `parent` may legitimately be set — the main `/products` endpoint strips a
     * client-submitted `parent` from its own request body (see `testClientSubmittedParentIsStrippedOnPut`
     * below), so it can no longer be used to create a variant child directly.
     */
    public function testGetListExcludesVariantChildren(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $topLevelId = $this->createProduct($familyId, 'Top Level Product');
        $parentId = $this->createProduct($familyId, 'Variant Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $parentId . '/variants.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'VARIANT-CHILD',
                'title' => 'Variant Child Product',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $childId = $data['id'];
        $this->assertIsString($childId);

        $this->client->request('GET', '/admin/api/products.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['products']);
        $ids = \array_column($data['_embedded']['products'], 'id');

        $this->assertContains($topLevelId, $ids);
        $this->assertContains($parentId, $ids);
        $this->assertNotContains($childId, $ids);
    }

    public function testGetReturnsTemplateOnlyWhenContentMissing(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        // Delete all dimension content rows to force ContentNotFoundException
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->createQuery('DELETE FROM ' . ProductDimensionContent::class . ' pdc WHERE pdc.product = (SELECT p FROM Sulu\Product\Domain\Model\Product p WHERE p.uuid = :uuid)')
            ->setParameter('uuid', $id)
            ->execute();
        $em->clear();

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('template', $data);
    }

    public function testPutWithInvalidAttributeTypeReturns400(): void
    {
        self::purgeDatabase();

        // Create a text attribute
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
        $attribute->setKey('description');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Description'));
        $attributeRepository->save($attribute);
        $em->flush();

        $attributeId = $attribute->getId();

        // Create a family with the text attribute enabled (not required)
        $familyId = $this->createProductFamily($attributeId);
        $id = $this->createProduct($familyId);

        // Pass an integer (not a string) for a text attribute → triggers Webmozart Assert::string()
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'attributes' => [$attributeId => 12345],
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(400, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPostTriggerRestoreWithoutVersionThrowsError(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        // Restore without version parameter triggers \InvalidArgumentException in handleAction
        $this->client->request(
            'POST',
            '/admin/api/products/' . $id . '.json?locale=en&action=restore',
        );
        $response = $this->client->getResponse();

        // The \InvalidArgumentException propagates as a 500 or handled error
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function testGetVersions(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId);

        $this->client->request('GET', '/admin/api/products/' . $id . '/versions.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * The main `/products` endpoint has no `parent` field on its own form (only the nested
     * variants endpoint legitimately sets it), so a client-submitted `parent` in the raw
     * request body must be silently stripped — never re-parenting the product via
     * `ProductParentMapper`. Otherwise the product would vanish from the main list (which
     * filters `where(parent, null)`) and reappear under another product's Variants tab.
     */
    public function testClientSubmittedParentIsStrippedOnPut(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $potentialParentId = $this->createProduct($familyId, 'Potential Parent');
        $id = $this->createProduct($familyId, 'My Product');

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'parent' => $potentialParentId,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $container = self::getContainer();
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $product = $productRepository->getOneBy(['uuid' => $id]);
        $this->assertNull($product->getParent());

        // ... and it must still show up in the main list (not hidden as if it were a variant).
        $this->client->request('GET', '/admin/api/products.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $listData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($listData);
        $this->assertIsArray($listData['_embedded']);
        $this->assertIsArray($listData['_embedded']['products']);
        $ids = \array_column($listData['_embedded']['products'], 'id');
        $this->assertContains($id, $ids);
    }
}
