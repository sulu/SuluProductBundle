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
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductFamilyController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductFamilyController::class)]
class ProductFamilyControllerTest extends SuluTestCase
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

    public function testGetEmptyList(): void
    {
        self::purgeDatabase();

        $this->client->request('GET', '/admin/api/product-families.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetListIncludesAuditFields(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/product-families.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'name' => 'Shoes', 'description' => null]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/product-families.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var array{_embedded: array{product_families: list<array<string, mixed>>}} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $item = $data['_embedded']['product_families'][0];

        $this->assertArrayHasKey('created', $item);
        $this->assertNotNull($item['created']);
        $this->assertArrayHasKey('changed', $item);
        $this->assertNotNull($item['changed']);
    }

    public function testPost(): string
    {
        $this->client->request(
            'POST',
            '/admin/api/product-families.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Shoes',
                'description' => 'Shoe family',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);
        $this->assertNotEmpty($id);
        $this->assertSame('Shoes', $data['name']);

        return $id;
    }

    #[Depends('testPost')]
    public function testGet(string $id): string
    {
        $this->client->request('GET', '/admin/api/product-families/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Shoes', $data['name']);
        $this->assertSame('Shoe family', $data['description']);
        $this->assertNull($data['externalIdentifier']);

        return $id;
    }

    #[Depends('testGet')]
    public function testPut(string $id): string
    {
        $this->client->request(
            'PUT',
            '/admin/api/product-families/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Boots',
                'description' => 'Boot family',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Boots', $data['name']);

        return $id;
    }

    #[Depends('testPut')]
    public function testDelete(string $id): void
    {
        $this->client->request('DELETE', '/admin/api/product-families/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testGetNotFound(): void
    {
        $this->client->request('GET', '/admin/api/product-families/non-existent-uuid.json?locale=en');
        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPutNotFound(): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/product-families/non-existent-uuid.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'name' => 'X', 'description' => null]) ?: null,
        );

        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testDeleteNotFound(): void
    {
        $this->client->request('DELETE', '/admin/api/product-families/non-existent-uuid.json?locale=en');
        $this->assertHttpStatusCode(404, $this->client->getResponse());
    }

    public function testPostWithFamilyAttributesRoundTrip(): void
    {
        self::purgeDatabase();

        $attributeId = $this->createAttribute('color', 'Color');

        $this->client->request(
            'POST',
            '/admin/api/product-families.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Apparel',
                'description' => null,
                'attributes' => [
                    $attributeId => ['enabled' => true, 'required' => true],
                ],
            ]) ?: null,
        );

        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $created = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($created);
        $familyId = $created['id'];
        $this->assertIsString($familyId);

        $this->client->request('GET', '/admin/api/product-families/' . $familyId . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['attributes']);
        $this->assertSame(
            [$attributeId => ['enabled' => true, 'required' => true]],
            $data['attributes'],
        );
    }

    private function createAttribute(string $key, string $name): int
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
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));
        $attributeRepository->save($attribute);

        $entityManager->flush();

        return $attribute->getId();
    }
}
