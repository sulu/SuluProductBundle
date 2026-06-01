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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Depends;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
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
        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'template' => 'product',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);
        $this->assertNotEmpty($id);

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
