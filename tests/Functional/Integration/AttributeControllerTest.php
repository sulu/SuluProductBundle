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
use Sulu\Product\UserInterface\Controller\Admin\AttributeController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeController::class)]
class AttributeControllerTest extends SuluTestCase
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

    private function createGroup(string $name = 'Test Group'): string
    {
        $this->client->request(
            'POST',
            '/admin/api/attribute-groups.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'name' => $name, 'description' => null, 'attributes' => []]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
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

    public function testGetListIncludesAuditFields(): void
    {
        self::purgeDatabase();
        $groupId = $this->createGroup();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/attributes.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var array{_embedded: array{attributes: list<array<string, mixed>>}} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $item = $data['_embedded']['attributes'][0];

        $this->assertArrayHasKey('created', $item);
        $this->assertNotNull($item['created']);
        $this->assertArrayHasKey('changed', $item);
        $this->assertNotNull($item['changed']);
    }

    public function testGetListFiltersByAttributeGroup(): void
    {
        self::purgeDatabase();
        $groupAId = $this->createGroup('Group A');
        $groupBId = $this->createGroup('Group B');

        foreach (['attr-a1', 'attr-a2'] as $key) {
            $this->client->request(
                'POST',
                '/admin/api/attributes.json?locale=en',
                [],
                [],
                [],
                \json_encode(['locale' => 'en', 'key' => $key, 'name' => $key, 'type' => 'text', 'group' => $groupAId]) ?: null,
            );
            $this->assertHttpStatusCode(201, $this->client->getResponse());
        }

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'key' => 'attr-b1', 'name' => 'attr-b1', 'type' => 'text', 'group' => $groupBId]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/attributes.json?locale=en&group=' . $groupAId);
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array{_embedded: array{attributes: list<array{key: string}>}} $data */
        $keys = \array_column($data['_embedded']['attributes'], 'key');
        \sort($keys);
        $this->assertSame(['attr-a1', 'attr-a2'], $keys);
    }

    public function testPost(): string
    {
        self::purgeDatabase();
        $groupId = $this->createGroup();

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
                'group' => $groupId,
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
                'position' => 0,
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
        $groupId = $this->createGroup();

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
                'group' => $groupId,
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

    public function testDerivesMeasurementFamilyFromStoredUnitAndDoesNotPersistIt(): void
    {
        self::purgeDatabase();
        $groupId = $this->createGroup();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'weight',
                'name' => 'Weight',
                'type' => 'number',
                'group' => $groupId,
                'measurementFamily' => 'weight',
                'config' => ['unit' => 'KILOGRAM'],
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

        $this->assertSame('weight', $data['measurementFamily']);

        $config = $data['config'];
        $this->assertIsArray($config);
        $this->assertSame('KILOGRAM', $config['unit']);
        $this->assertArrayNotHasKey('measurementFamily', $config);
    }

    public function testMeasurementFamilyIsNullWhenNoUnitStored(): void
    {
        self::purgeDatabase();
        $groupId = $this->createGroup();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'note',
                'name' => 'Note',
                'type' => 'text',
                'group' => $groupId,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $postData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($postData);
        $id = $postData['id'];
        $this->assertIsString($id);

        $this->client->request('GET', '/admin/api/attributes/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNull($data['measurementFamily']);
    }

    public function testPersistsFormatInConfig(): void
    {
        self::purgeDatabase();
        $groupId = $this->createGroup();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'insulation-resistance',
                'name' => 'Insulation resistance',
                'type' => 'number',
                'group' => $groupId,
                'config' => ['format' => '> %value% GΩ'],
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

        $config = $data['config'];
        $this->assertIsArray($config);
        $this->assertSame('> %value% GΩ', $config['format']);
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
                'position' => 0,
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
        $groupId = $this->createGroup();

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
                'group' => $groupId,
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
                'group' => $groupId,
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
        $groupId = $this->createGroup();

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
                'group' => $groupId,
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
                'group' => $groupId,
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
                'position' => 0,
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

    public function testGetFallsBackToDefaultLocaleForTranslation(): void
    {
        self::purgeDatabase();
        $groupId = $this->createGroup();

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'key' => 'weight',
                'name' => 'Weight',
                'type' => 'options',
                'group' => $groupId,
                'options' => [
                    ['key' => 'heavy', 'name' => 'Heavy'],
                ],
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $postData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($postData);
        $id = $postData['id'];
        $this->assertIsString($id);

        // Fetch in a locale with no translation → falls back to defaultLocale ('en')
        $this->client->request('GET', '/admin/api/attributes/' . $id . '.json?locale=fr');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        // Attribute name falls back to English
        $this->assertSame('Weight', $data['name']);
        // Option name also falls back to English
        $options = $data['options'];
        $this->assertIsArray($options);
        $this->assertCount(1, $options);
        $this->assertIsArray($options[0]);
        $this->assertSame('Heavy', $options[0]['name']);
    }
}
