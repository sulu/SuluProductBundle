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
use Sulu\Product\UserInterface\Controller\Admin\AttributeGroupController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeGroupController::class)]
class AttributeGroupControllerTest extends SuluTestCase
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

        $this->client->request('GET', '/admin/api/attribute-groups.json?locale=en');
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
            '/admin/api/attribute-groups.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'name' => 'Dimensions', 'description' => null, 'attributes' => []]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/attribute-groups.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var array{_embedded: array{attribute_groups: list<array<string, mixed>>}} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $item = $data['_embedded']['attribute_groups'][0];

        $this->assertArrayHasKey('created', $item);
        $this->assertNotNull($item['created']);
        $this->assertArrayHasKey('changed', $item);
        $this->assertNotNull($item['changed']);
    }

    public function testPost(): string
    {
        $this->client->request(
            'POST',
            '/admin/api/attribute-groups.json?locale=en',
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
        $this->client->request('GET', '/admin/api/attribute-groups/' . $id . '.json?locale=en');
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
            '/admin/api/attribute-groups/' . $id . '.json?locale=en',
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
        $this->client->request('DELETE', '/admin/api/attribute-groups/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(204, $response);
    }

    public function testGetNotFound(): void
    {
        $this->client->request('GET', '/admin/api/attribute-groups/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testPutNotFound(): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/attribute-groups/non-existent-uuid.json?locale=en',
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
        $this->client->request('DELETE', '/admin/api/attribute-groups/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(404, $response);
    }

    public function testDeleteNonEmpty(): void
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/attribute-groups.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'name' => 'Non-Empty Group', 'description' => null, 'attributes' => []]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $groupData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($groupData);
        $groupId = $groupData['id'];
        $this->assertIsString($groupId);

        $this->client->request(
            'POST',
            '/admin/api/attributes.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'key' => 'del-group-attr', 'name' => 'Attr', 'type' => 'text', 'group' => $groupId]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        $this->client->request('DELETE', '/admin/api/attribute-groups/' . $groupId . '.json?locale=en');
        $response = $this->client->getResponse();

        $this->assertHttpStatusCode(409, $response);
    }
}
