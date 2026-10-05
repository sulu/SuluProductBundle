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

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Trash;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Trash\AttributeTrashItemHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeTrashItemHandler::class)]
class AttributeTrashItemHandlerTest extends SuluTestCase
{
    use TrashTestTrait;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testRemoveAndRestore(): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color',
            'name' => 'Color',
            'description' => 'The color',
            'type' => 'options',
            'group' => $groupId,
            'localized' => true,
            'filterable' => true,
            'options' => [['key' => 'red', 'name' => 'Red'], ['key' => 'blue', 'name' => 'Blue']],
        ]);
        /** @var array{options: list<array{id: string, key: string}>} $attribute */
        $attribute = $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en');
        $this->requestJson('PUT', '/admin/api/attributes/' . $attributeId . '.json?locale=de', [
            'key' => 'color',
            'name' => 'Farbe',
            'type' => 'options',
            'group' => $groupId,
            'options' => [
                ['id' => $attribute['options'][0]['id'], 'key' => 'red', 'name' => 'Rot'],
                ['id' => $attribute['options'][1]['id'], 'key' => 'blue', 'name' => 'Blau'],
            ],
        ]);
        $familyId = $this->postForId('/admin/api/product-families.json?locale=en', [
            'key' => 'apparel',
            'name' => 'Apparel',
            'attributes' => [['id' => $attributeId, 'required' => true, 'variantSpecific' => true]],
        ]);

        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());
        $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en', null, 404);

        $restored = $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId));
        $this->assertRestoreNavigatesTo('sulu_product.attribute_trash_item_handler', $restored, $attributeId);

        /** @var array{key: string, name: string, group: string, localized: bool, filterable: bool, options: list<array{id: string, key: string, name: string}>} $data */
        $data = $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=de');
        $this->assertSame('color', $data['key']);
        $this->assertSame('Farbe', $data['name']);
        $this->assertSame($groupId, $data['group']);
        $this->assertTrue($data['localized']);
        $this->assertTrue($data['filterable']);
        $this->assertSame(
            [[$attribute['options'][0]['id'], 'red', 'Rot'], [$attribute['options'][1]['id'], 'blue', 'Blau']],
            \array_map(static fn (array $option) => [$option['id'], $option['key'], $option['name']], $data['options']),
        );
        $this->assertSame('Color', $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en')['name']);

        $family = $this->requestJson('GET', '/admin/api/product-families/' . $familyId . '.json?locale=en');
        $this->assertSame([['id' => $attributeId, 'required' => true, 'variantSpecific' => true]], $family['attributes']);

        $this->assertSame(
            ['created', 'modified', 'removed', 'restored'],
            $this->findActivityTypes(AttributeInterface::RESOURCE_KEY, $attributeId),
        );
    }

    public function testRestoreFailsWhenKeyIsTaken(): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);

        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->requestJson('POST', '/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Colour', 'type' => 'text', 'group' => $groupId,
        ], 201);

        $error = $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId), 409);
        $this->assertSame('The key "color" is already used by another attribute.', $error['detail'] ?? null);
    }

    public function testRestoreFailsWhenGroupIsGone(): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);

        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->client->request('DELETE', '/admin/api/attribute-groups/' . $groupId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId), 404);
    }
}
