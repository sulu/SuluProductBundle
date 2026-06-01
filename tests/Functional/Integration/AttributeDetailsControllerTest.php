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
use Sulu\Product\UserInterface\Controller\Admin\AttributeDetailsController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeDetailsController::class)]
class AttributeDetailsControllerTest extends SuluTestCase
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

        $this->client->request('GET', '/admin/api/attributes.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testPost(): string
    {
        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'color',
                'name' => 'Color',
                'type' => 'text',
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
        $this->client->request('GET', '/admin/api/attributes/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('color', $data['key']);

        return $id;
    }

    #[Depends('testGet')]
    public function testPut(string $id): string
    {
        $this->client->request(
            'PUT',
            '/admin/api/attributes/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'colour',
                'name' => 'Colour',
                'type' => 'text',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('colour', $data['key']);

        return $id;
    }

    #[Depends('testPut')]
    public function testDelete(string $id): void
    {
        $this->client->request('DELETE', '/admin/api/attributes/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testGetSerializesOptions(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'size',
                'name' => 'Size',
                'type' => 'options',
                'options' => [
                    ['key' => 'small', 'name' => 'Small'],
                    ['key' => 'large', 'name' => 'Large'],
                ],
            ]) ?: null,
        );
        $postResponse = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $postResponse);
        $postData = \json_decode((string) $postResponse->getContent(), true);
        $this->assertIsArray($postData);
        $id = $postData['id'];
        $this->assertIsString($id);

        $this->client->request('GET', '/admin/api/attributes/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array{options: list<array{type: string, key: string, name: string}>} $data */
        $this->assertCount(2, $data['options']);
        $this->assertSame('option', $data['options'][0]['type']);
        $this->assertSame('small', $data['options'][0]['key']);
        $this->assertSame('Small', $data['options'][0]['name']);
    }

    public function testGetNotFound(): void
    {
        $this->client->request('GET', '/admin/api/attributes/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testPutNotFound(): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/attributes/non-existent-uuid.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'color',
                'name' => 'Color',
                'type' => 'text',
            ]) ?: null,
        );

        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testDeleteNotFound(): void
    {
        $this->client->request('DELETE', '/admin/api/attributes/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testPostDuplicate(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'post-dup-unique',
                'name' => 'Post Dup Unique',
                'type' => 'text',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'post-dup-unique',
                'name' => 'Post Dup Unique Duplicate',
                'type' => 'text',
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(409, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $detail = $data['detail'];
        $this->assertIsString($detail);
        $this->assertStringContainsString('post-dup-unique', $detail);
    }

    public function testPutDuplicate(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'put-dup-first',
                'name' => 'Put Dup First',
                'type' => 'text',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'put-dup-second',
                'name' => 'Put Dup Second',
                'type' => 'text',
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $secondData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($secondData);
        $secondId = $secondData['id'];
        $this->assertIsString($secondId);

        $this->client->request(
            'PUT',
            '/admin/api/attributes/' . $secondId . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'put-dup-first',
                'name' => 'Put Dup First Duplicate',
                'type' => 'text',
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(409, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $detail = $data['detail'];
        $this->assertIsString($detail);
        $this->assertStringContainsString('put-dup-first', $detail);
    }
}
