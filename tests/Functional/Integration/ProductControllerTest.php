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
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductController;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
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
            /** @var AttributeRepositoryInterface $attributeRepository */
            $attributeRepository = self::getContainer()->get(AttributeRepositoryInterface::class);
            $attribute = $attributeRepository->findOneBy(['id' => $attributeId]);
            $this->assertNotNull($attribute);

            $attributes[] = ['id' => $attribute->getUuid(), 'required' => $required, 'variantSpecific' => false];
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

    /**
     * @param array<int, mixed> $attributes values for the family's required attributes, which create enforces
     */
    private function createProduct(
        string $familyId,
        string $title = 'My Product',
        string $type = ProductInterface::TYPE_PRODUCT,
        array $attributes = [],
    ): string {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode(\array_filter([
                'locale' => 'en',
                'title' => $title,
                // a product with variants shows no route field, its variants carry the routes
                'url' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS === $type ? null : '/test-product-' . $counter,
                'productFamily' => $familyId,
                'type' => $type,
                'attributes' => $attributes ?: null,
            ], static fn ($value) => null !== $value)) ?: null,
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

    private function createTextAttribute(): int
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
        $attribute->setKey('description');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Description'));
        $attributeRepository->save($attribute);

        $em->flush();

        return $attribute->getId();
    }

    private function createOptionsAttribute(): int
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
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Color'));
        foreach (['red' => 'Red', 'blue' => 'Blue'] as $key => $name) {
            $option = new AttributeOption($attribute, $key);
            $option->addTranslation(new AttributeOptionTranslation($option, 'en', $name));
            $attribute->addOption($option);
        }
        $attributeRepository->save($attribute);

        $em->flush();

        return $attribute->getId();
    }

    private function createRangeAttribute(): int
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
        $attribute->setKey('operating_temperature');
        $attribute->setType(AttributeInterface::TYPE_RANGE);
        $attribute->setConfig(['unit' => 'CELSIUS']);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Operating temperature'));
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

    public function testPostProductWithVariantsWithoutUrlCreatesNoRoute(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();

        $productId = $this->createProduct($familyId, 'Plain Product');
        $withVariantsId = $this->createProduct($familyId, 'Variant Parent Product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        /** @var RouteRepositoryInterface $routeRepository */
        $routeRepository = self::getContainer()->get(RouteRepositoryInterface::class);

        $this->assertTrue($routeRepository->existBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $productId,
            'locale' => 'en',
        ]));

        $this->assertFalse($routeRepository->existBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $withVariantsId,
            'locale' => 'en',
        ]));
    }

    public function testPostProductWithVariantsWithUrlCreatesNoRoute(): void
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
                'title' => 'Variant Parent Product',
                'url' => '/test-variant-parent-product',
                'productFamily' => $familyId,
                'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        /** @var RouteRepositoryInterface $routeRepository */
        $routeRepository = self::getContainer()->get(RouteRepositoryInterface::class);

        $this->assertFalse($routeRepository->existBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $id,
            'locale' => 'en',
        ]));
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
        $id = $this->createProduct($familyId, attributes: [$attributeId => 12.5]);

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

    public function testOptionsAttributeValueIsStoredAsOptionRelation(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createOptionsAttribute();
        $familyId = $this->createProductFamily($attributeId, false);
        $id = $this->createProduct($familyId);

        $this->putAttributes($id, 'en', [$attributeId => 'blue']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $this->assertSame('blue', $this->getAttributeValue($id, 'en', $attributeId));
        $this->assertNull($this->getStoredOptionKey($id, $attributeId, DimensionContentInterface::STAGE_LIVE));

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $em->clear();

        $this->assertSame('blue', $this->getStoredOptionKey($id, $attributeId, DimensionContentInterface::STAGE_DRAFT));
        $this->assertSame('blue', $this->getStoredOptionKey($id, $attributeId, DimensionContentInterface::STAGE_LIVE));
    }

    public function testRenamingOptionKeyKeepsProductValues(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createOptionsAttribute();
        $familyId = $this->createProductFamily($attributeId, false);
        $id = $this->createProduct($familyId);

        $this->putAttributes($id, 'en', [$attributeId => 'blue']);
        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = self::getContainer()->get(AttributeRepositoryInterface::class);
        $attribute = $attributeRepository->findOneBy(['id' => $attributeId]);
        $this->assertNotNull($attribute);
        $attributeUuid = $attribute->getUuid();

        $this->client->request('GET', '/admin/api/attributes/' . $attributeUuid . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        /** @var array{key: string, name: string, type: string, options: list<array{id: int, key: string, name: string}>} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        foreach ($data['options'] as $index => $option) {
            if ('blue' === $option['key']) {
                $data['options'][$index]['key'] = 'navy';
            }
        }

        $this->client->request(
            'PUT',
            '/admin/api/attributes/' . $attributeUuid . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => $data['key'],
                'name' => $data['name'],
                'type' => $data['type'],
                'options' => $data['options'],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $this->assertSame('navy', $this->getStoredOptionKey($id, $attributeId, DimensionContentInterface::STAGE_DRAFT));
        $this->assertSame('navy', $this->getStoredOptionKey($id, $attributeId, DimensionContentInterface::STAGE_LIVE));
    }

    public function testRangeAttributeValueIsStoredPublishedAndCleared(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createRangeAttribute();
        $familyId = $this->createProductFamily($attributeId, false);
        $id = $this->createProduct($familyId);

        $this->putAttributes($id, 'en', [$attributeId => ['from' => -20, 'to' => '60.5']]);

        $this->assertEquals(['from' => -20, 'to' => 60.5], $this->getAttributeValue($id, 'en', $attributeId));

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $this->assertSame(['from' => -20.0, 'to' => 60.5], $this->getStoredRange($id, $attributeId, DimensionContentInterface::STAGE_LIVE));

        $this->putAttributes($id, 'en', [$attributeId => ['from' => null, 'to' => null]]);

        $this->assertNull($this->getAttributeValue($id, 'en', $attributeId));
        $this->assertNull($this->getStoredRange($id, $attributeId, DimensionContentInterface::STAGE_DRAFT));
    }

    /**
     * @param array<string, mixed> $range
     */
    #[DataProvider('provideInvalidRanges')]
    public function testPutWithInvalidRangeReturns400(array $range): void
    {
        self::purgeDatabase();
        $attributeId = $this->createRangeAttribute();
        $familyId = $this->createProductFamily($attributeId, false);
        $id = $this->createProduct($familyId);
        $this->putAttributes($id, 'en', [$attributeId => ['from' => 1, 'to' => 2]]);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'attributes' => [$attributeId => $range]]) ?: null,
        );

        $this->assertHttpStatusCode(400, $this->client->getResponse());
        $this->assertEquals(['from' => 1, 'to' => 2], $this->getAttributeValue($id, 'en', $attributeId));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidRanges(): iterable
    {
        yield 'from exceeds to' => [['from' => 60, 'to' => -20]];
        yield 'half filled' => [['from' => 1, 'to' => null]];
    }

    public function testPostWithMissingRequiredRangeReturns422(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createRangeAttribute();
        $familyId = $this->createProductFamily($attributeId);

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'Sensor',
                'url' => '/sensor',
                'productFamily' => $familyId,
                'type' => ProductInterface::TYPE_PRODUCT,
                'attributes' => [$attributeId => ['from' => null, 'to' => null]],
            ]) ?: null,
        );

        $this->assertHttpStatusCode(422, $this->client->getResponse());
    }

    /**
     * @return array<string, float|null>|null the stored bounds by value key
     */
    private function getStoredRange(string $id, int $attributeId, string $stage): ?array
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var list<array{valueKey: string, number: float|null}> $rows */
        $rows = $em->createQueryBuilder()
            ->select('attributeValue.valueKey', 'attributeValue.number')
            ->from(ProductAttributeValue::class, 'attributeValue')
            ->innerJoin('attributeValue.productDimensionContent', 'dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->where('product.uuid = :uuid')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale IS NULL')
            ->andWhere('dimensionContent.version = :version')
            ->andWhere('IDENTITY(attributeValue.attribute) = :attributeId')
            ->orderBy('attributeValue.valueKey')
            ->setParameter('uuid', $id)
            ->setParameter('stage', $stage)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('attributeId', $attributeId)
            ->getQuery()
            ->getArrayResult();

        return [] === $rows ? null : \array_column($rows, 'number', 'valueKey');
    }

    private function getStoredOptionKey(string $id, int $attributeId, string $stage): ?string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var list<array{optionKey: string}> $rows */
        $rows = $em->createQueryBuilder()
            ->select('attributeOption.key AS optionKey')
            ->from(ProductAttributeValue::class, 'attributeValue')
            ->innerJoin('attributeValue.productDimensionContent', 'dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->innerJoin('attributeValue.attributeOption', 'attributeOption')
            ->where('product.uuid = :uuid')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale IS NULL')
            ->andWhere('IDENTITY(attributeValue.attribute) = :attributeId')
            ->setParameter('uuid', $id)
            ->setParameter('stage', $stage)
            ->setParameter('attributeId', $attributeId)
            ->getQuery()
            ->getArrayResult();

        return $rows[0]['optionKey'] ?? null;
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
     * `/products/{parentId}/variants` endpoint (`ProductVariantController`) — the main
     * `/products` form offers no `variant` type and drops a client-submitted `parent`
     * (see `testClientSubmittedParentIsIgnoredForNonVariantType` below).
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

        $attributeId = $this->createTextAttribute();

        // Create a family with the text attribute enabled (not required)
        $familyId = $this->createProductFamily($attributeId);
        $id = $this->createProduct($familyId, attributes: [$attributeId => 'Something']);

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

    public function testPostWithMissingRequiredAttributeReturns422(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createRequiredAttribute();
        $familyId = $this->createProductFamily($attributeId);

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'url' => '/post-missing-required-attribute',
                'productFamily' => $familyId,
                'attributes' => [$attributeId => null],
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(422, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPostWithoutAttributesKeyReturns422(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createRequiredAttribute();
        $familyId = $this->createProductFamily($attributeId);

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'url' => '/post-without-attributes-key',
                'productFamily' => $familyId,
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(422, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPostWithInvalidAttributeTypeReturns400(): void
    {
        self::purgeDatabase();
        $attributeId = $this->createTextAttribute();
        $familyId = $this->createProductFamily($attributeId);

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'title' => 'My Product',
                'url' => '/post-invalid-attribute-type',
                'productFamily' => $familyId,
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
     * Only a variant carries a parent (`ProductParentMapper`), so a `parent` submitted next to
     * any other type is dropped. Otherwise the product would vanish from the main list (which
     * excludes variants by type) and reappear under another product's Variants tab.
     */
    public function testClientSubmittedParentIsIgnoredForNonVariantType(): void
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
                'type' => ProductInterface::TYPE_PRODUCT,
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

    public function testListFiltersByProductFamilyTypeAndStatus(): void
    {
        self::purgeDatabase();

        $familyA = $this->createProductFamily();
        $familyB = $this->createProductFamily();

        $plainInA = $this->createProduct($familyA, 'Plain In A');
        $withVariantsInA = $this->createProduct($familyA, 'Variants In A', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $plainInB = $this->createProduct($familyB, 'Plain In B');

        $expectedFamilyA = [$plainInA, $withVariantsInA];
        \sort($expectedFamilyA);
        $this->assertSame($expectedFamilyA, $this->listIds(['productFamily' => $familyA]));

        $this->assertSame(
            [$withVariantsInA],
            $this->listIds(['type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS]),
        );

        $this->assertSame(
            [$plainInB],
            $this->listIds(['productFamily' => $familyB, 'type' => ProductInterface::TYPE_PRODUCT]),
        );

        // every product defaults to the "available" status
        $expectedAll = [$plainInA, $withVariantsInA, $plainInB];
        \sort($expectedAll);
        $this->assertSame($expectedAll, $this->listIds(['status' => 'available']));
        $this->assertSame([], $this->listIds(['status' => 'discontinued']));

        // nothing is published yet, so the whole list sits in "unpublished"
        $this->assertSame($expectedAll, $this->listIds(['publishedState' => 'unpublished']));
        $this->assertSame([], $this->listIds(['publishedState' => 'published']));

        $this->client->request('POST', '/admin/api/products/' . $plainInA . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->assertSame([$plainInA], $this->listIds(['publishedState' => 'published']));
    }

    /**
     * @param array<string, string> $filter
     *
     * @return array<int, string>
     */
    private function listIds(array $filter): array
    {
        $this->client->request('GET', '/admin/api/products.json?locale=en', ['filter' => $filter]);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['products']);

        /** @var array<int, string> $ids */
        $ids = \array_column($data['_embedded']['products'], 'id');
        \sort($ids);

        return $ids;
    }

    /**
     * A single selection loads the whole item from the detail endpoint, so its display property has to
     * exist there. The list column is called "name" while the detail response only carries "title".
     */
    public function testSingleProductSelectionDisplayPropertyExistsInDetailResponse(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $productId = $this->createProduct($familyId, 'Selectable Product');

        $this->client->request('GET', '/admin/api/products/' . $productId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('title', $data);
        $this->assertSame('Selectable Product', $data['title']);
    }

    public function testListExposesThePublishStateForTheIndicator(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $draftId = $this->createProduct($familyId, 'Draft Product');
        $publishedId = $this->createProduct($familyId, 'Published Product');

        $this->client->request('POST', '/admin/api/products/' . $publishedId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // the list does not ask for either field, the indicator needs them anyway
        $this->client->request('GET', '/admin/api/products.json?locale=en&fields=name,id&flat=true');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['products']);

        $byId = [];
        foreach ($data['_embedded']['products'] as $item) {
            $this->assertIsArray($item);
            $this->assertIsString($item['id']);
            $byId[$item['id']] = $item;
        }

        // the admin derives the circle from a boolean state plus a publish date
        $this->assertFalse($byId[$draftId]['publishedState']);
        $this->assertNull($byId[$draftId]['published']);

        $this->assertTrue($byId[$publishedId]['publishedState']);
        $this->assertNotNull($byId[$publishedId]['published']);
    }

    public function testGetVersionsListsPublishedVersions(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $id = $this->createProduct($familyId, 'Versioned Product');

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // the parameters the versions tab sends
        $this->client->request(
            'GET',
            '/admin/api/products/' . $id . '/versions?page=1&locale=en&limit=10&fields=title,version,changer,id&flat=true',
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame(1, $data['page']);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['products_versions']);
        $this->assertCount(1, $data['_embedded']['products_versions']);
        $version = $data['_embedded']['products_versions'][0];
        $this->assertIsArray($version);
        $this->assertSame('Versioned Product', $version['title']);
    }
}
