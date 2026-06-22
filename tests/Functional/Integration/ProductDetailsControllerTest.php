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
use PHPUnit\Framework\Attributes\Depends;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductDetailsController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductDetailsController::class)]
class ProductDetailsControllerTest extends SuluTestCase
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

    private function createProductFamily(): string
    {
        $container = self::getContainer();

        /** @var ProductFamilyRepositoryInterface $familyRepository */
        $familyRepository = $container->get(ProductFamilyRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $family = $familyRepository->create();
        $familyRepository->save($family);
        $entityManager->flush();

        $uuid = $family->getUuid();
        self::assertNotNull($uuid);

        return $uuid;
    }

    /**
     * @return array{family: string, attribute: string}
     */
    private function createFamilyWithRequiredNumberAttribute(): array
    {
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var ProductFamilyRepositoryInterface $familyRepository */
        $familyRepository = $container->get(ProductFamilyRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->create();
        $groupRepository->save($group);

        $attribute = $attributeRepository->create($group);
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attributeRepository->save($attribute);

        $family = $familyRepository->create();
        $familyAttribute = new ProductFamilyAttribute($family, $attribute);
        $familyAttribute->setRequired(true);
        $family->addFamilyAttribute($familyAttribute);
        $familyRepository->save($family);

        $entityManager->flush();

        $uuid = $family->getUuid();
        self::assertNotNull($uuid);

        return ['family' => $uuid, 'attribute' => $attribute->getKey()];
    }

    private function createProductInFamily(string $familyId): string
    {
        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'template' => 'product',
                'productFamily' => $familyId,
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    public function testPutPersistsAttributeValueAndGetSerializesIt(): void
    {
        self::purgeDatabase();

        $fixture = $this->createFamilyWithRequiredNumberAttribute();
        $id = $this->createProductInFamily($fixture['family']);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'attributes' => [$fixture['attribute'] => 7.5],
            ]) ?: null,
        );

        $putResponse = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $putResponse);

        $putData = \json_decode((string) $putResponse->getContent(), true);
        $this->assertIsArray($putData);
        $this->assertIsArray($putData['attributes']);
        $this->assertSame(7.5, $putData['attributes'][$fixture['attribute']]);

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $getResponse = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $getResponse);

        $getData = \json_decode((string) $getResponse->getContent(), true);
        $this->assertIsArray($getData);
        $this->assertIsArray($getData['attributes']);
        $this->assertSame(7.5, $getData['attributes'][$fixture['attribute']]);
    }

    public function testGetSerializesEmptyAttributesAsObject(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $id = $this->createProductInFamily($familyId);

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        // `attributes` must serialize as a JSON object ({}), never an empty array ([]).
        // The admin form store writes field values via json-pointer; with an array parent a
        // numeric leaf (the attribute id) becomes an array index and mobx throws
        // "[mobx.array] Index out of bounds" when the user enters a value.
        $content = (string) $response->getContent();
        $this->assertStringContainsString('"attributes":{}', $content);
        $this->assertStringNotContainsString('"attributes":[]', $content);
    }

    public function testGetPreseedsEnabledAttributeKeysWithNull(): void
    {
        self::purgeDatabase();

        $fixture = $this->createFamilyWithRequiredNumberAttribute();
        $id = $this->createProductInFamily($fixture['family']);

        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        // Every enabled family attribute must be present (defaulting to null) so the admin form
        // data carries all field keys up front: mobx 4 does not react to keys added after the data
        // became observable, so unseeded fields would only render their value after a blur.
        $getData = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($getData);
        $this->assertIsArray($getData['attributes']);
        $this->assertArrayHasKey($fixture['attribute'], $getData['attributes']);
        $this->assertNull($getData['attributes'][$fixture['attribute']]);
    }

    public function testPutWithMissingRequiredAttributeReturns422(): void
    {
        self::purgeDatabase();

        $fixture = $this->createFamilyWithRequiredNumberAttribute();
        $id = $this->createProductInFamily($fixture['family']);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'attributes' => [$fixture['attribute'] => null],
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(422, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testPutWithInvalidAttributeValueTypeReturns400(): void
    {
        self::purgeDatabase();

        $fixture = $this->createFamilyWithRequiredNumberAttribute();
        $id = $this->createProductInFamily($fixture['family']);

        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'attributes' => [$fixture['attribute'] => ['not', 'a', 'number']],
            ]) ?: null,
        );

        $this->assertHttpStatusCode(400, $this->client->getResponse());
    }

    public function testGetEmptyList(): void
    {
        self::purgeDatabase();

        $this->client->request('GET', '/admin/api/products.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        $this->assertSame([], $embedded['products']);
    }

    public function testDeleteLocale(): void
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
                'template' => 'product',
                'productFamily' => $this->createProductFamily(),
            ]) ?: null,
        );

        $postResponse = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $postResponse);

        $data = \json_decode((string) $postResponse->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        $this->client->request('DELETE', '/admin/api/products/' . $id . '.json?locale=en&deleteLocale=true');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testPost(): string
    {
        $familyId = $this->createProductFamily();

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'template' => 'product',
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
        $this->assertSame($familyId, $data['productFamily']);

        return $id;
    }

    #[Depends('testPost')]
    public function testGet(string $id): string
    {
        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        return $id;
    }

    #[Depends('testGet')]
    public function testPut(string $id): string
    {
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'PROD-001',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('PROD-001', $data['code']);

        return $id;
    }

    public function testGetInvalidIdReturns404(): void
    {
        $this->client->request('GET', '/admin/api/products/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testPutNotFound(): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/products/non-existent-uuid.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'code' => 'PROD-999',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    #[Depends('testPut')]
    public function testDelete(string $id): void
    {
        $this->client->request('DELETE', '/admin/api/products/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }
}
