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
use Sulu\Product\UserInterface\Controller\Admin\AttributeSetController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeSetController::class)]
class AttributeSetControllerTest extends SuluTestCase
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

        $this->client->request('GET', '/admin/api/attribute-sets.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testPost(): string
    {
        $this->client->request(
            'POST',
            '/admin/api/attribute-sets.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Colors',
                'description' => 'Color attributes',
                'attributes' => [],
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
        $this->client->request('GET', '/admin/api/attribute-sets/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Colors', $data['name']);
        $this->assertSame('Color attributes', $data['description']);
        $this->assertNull($data['externalIdentifier']);
        $this->assertSame([], $data['attributes']);

        return $id;
    }

    #[Depends('testGet')]
    public function testPut(string $id): string
    {
        $this->client->request(
            'PUT',
            '/admin/api/attribute-sets/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Sizes',
                'description' => 'Size attributes',
                'attributes' => [],
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Sizes', $data['name']);

        return $id;
    }

    #[Depends('testPut')]
    public function testDelete(string $id): void
    {
        $this->client->request('DELETE', '/admin/api/attribute-sets/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testGetNotFound(): void
    {
        $this->client->request('GET', '/admin/api/attribute-sets/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testPutNotFound(): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/attribute-sets/non-existent-uuid.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Colors',
                'description' => null,
                'attributes' => [],
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testDeleteNotFound(): void
    {
        $this->client->request('DELETE', '/admin/api/attribute-sets/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }
}
