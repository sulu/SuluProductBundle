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

use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Trash\AttributeTrashItemHandler;
use Sulu\Product\Infrastructure\Sulu\Trash\ProductFamilyTrashItemHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeTrashItemHandler::class)]
#[CoversClass(ProductFamilyTrashItemHandler::class)]
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

        self::getEntityManager()->createQueryBuilder()
            ->update(Attribute::class, 'entity')
            ->set('entity.externalIdentifier', ':externalIdentifier')
            ->set('entity.created', ':created')
            ->set('entity.config', ':config')
            ->where('entity.uuid = :uuid')
            ->setParameter('externalIdentifier', 'erp-1')
            ->setParameter('config', ['source' => 'erp'], Types::JSON)
            ->setParameter('created', new \DateTimeImmutable('2020-01-02 03:04:05'), Types::DATETIME_IMMUTABLE)
            ->setParameter('uuid', $attributeId)
            ->getQuery()
            ->execute();
        foreach (['red' => 7, 'blue' => 3] as $key => $position) {
            self::getEntityManager()->createQueryBuilder()
                ->update(AttributeOption::class, 'option')
                ->set('option.position', ':position')
                ->where('option.key = :key')
                ->setParameter('position', $position)
                ->setParameter('key', $key)
                ->getQuery()
                ->execute();
        }

        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());
        $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en', null, 404);

        $restored = $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId));
        $this->assertRestoreNavigatesTo('sulu_product.attribute_trash_item_handler', $restored, $attributeId);

        /** @var array{key: string, type: string, config: array<string, mixed>, externalIdentifier: string|null, name: string, description: string|null, group: string, localized: bool, filterable: bool, options: list<array{id: string, key: string, name: string}>} $data */
        $data = $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=de');
        $this->assertSame('color', $data['key']);
        $this->assertSame('options', $data['type']);
        $this->assertSame(['source' => 'erp'], $data['config']);
        $this->assertSame('erp-1', $data['externalIdentifier']);
        $this->assertSame('Farbe', $data['name']);
        $this->assertSame($groupId, $data['group']);
        $this->assertTrue($data['localized']);
        $this->assertTrue($data['filterable']);
        $this->assertSame(
            [[$attribute['options'][1]['id'], 'blue', 'Blau'], [$attribute['options'][0]['id'], 'red', 'Rot']],
            \array_map(static fn (array $option) => [$option['id'], $option['key'], $option['name']], $data['options']),
        );
        $en = $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en');
        $this->assertSame('Color', $en['name']);
        $this->assertSame('The color', $en['description']);

        $restoredEntity = $this->getClearedEntityManager()->find(AttributeInterface::class, $attributeId);
        $this->assertNotNull($restoredEntity);
        $this->assertSame('2020-01-02 03:04:05', $restoredEntity->getCreated()->format('Y-m-d H:i:s'));
        $this->assertSame(
            ['blue' => 3, 'red' => 7],
            \array_column(\array_map(static fn (AttributeOptionInterface $option) => [$option->getKey(), $option->getPosition()], $restoredEntity->getOptions()), 1, 0),
        );

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

    public function testRestoreTakesBackItsPosition(): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $colorId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);
        $sizeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'size', 'name' => 'Size', 'type' => 'text', 'group' => $groupId,
        ]);
        $colorPosition = $this->requestJson('GET', '/admin/api/attributes/' . $colorId . '.json?locale=en')['position'];
        $sizePosition = $this->requestJson('GET', '/admin/api/attributes/' . $sizeId . '.json?locale=en')['position'];
        $this->assertIsInt($colorPosition);
        $this->assertIsInt($sizePosition);
        $this->assertGreaterThan($colorPosition, $sizePosition);

        $this->client->request('DELETE', '/admin/api/attributes/' . $colorId . '.json');
        $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $colorId));

        $this->assertSame($colorPosition, $this->requestJson('GET', '/admin/api/attributes/' . $colorId . '.json?locale=en')['position']);
        $this->assertSame($sizePosition + 1, $this->requestJson('GET', '/admin/api/attributes/' . $sizeId . '.json?locale=en')['position']);
    }

    public function testRestoreSkipsLinkToRemovedFamily(): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);
        $familyId = $this->postForId('/admin/api/product-families.json?locale=en', [
            'key' => 'apparel',
            'name' => 'Apparel',
            'attributes' => [['id' => $attributeId, 'required' => true, 'variantSpecific' => false]],
        ]);

        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->client->request('DELETE', '/admin/api/product-families/' . $familyId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId));

        $this->assertSame('color', $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en')['key']);
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function provideRemoveAndRestoreOrders(): iterable
    {
        yield 'attribute removed first, attribute restored first' => [true, true];
        yield 'attribute removed first, family restored first' => [true, false];
        yield 'family removed first, attribute restored first' => [false, true];
        yield 'family removed first, family restored first' => [false, false];
    }

    #[DataProvider('provideRemoveAndRestoreOrders')]
    public function testRestoreKeepsLinkWhenBothAreRemoved(bool $removeAttributeFirst, bool $restoreAttributeFirst): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);
        $familyId = $this->postForId('/admin/api/product-families.json?locale=en', [
            'key' => 'apparel',
            'name' => 'Apparel',
            'attributes' => [['id' => $attributeId, 'required' => true, 'variantSpecific' => true]],
        ]);

        $attributeUri = '/admin/api/attributes/' . $attributeId . '.json';
        $familyUri = '/admin/api/product-families/' . $familyId . '.json';
        foreach ($removeAttributeFirst ? [$attributeUri, $familyUri] : [$familyUri, $attributeUri] as $uri) {
            $this->client->request('DELETE', $uri);
            $this->assertHttpStatusCode(204, $this->client->getResponse());
        }

        $attributeTrashItemId = $this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId);
        $familyTrashItemId = $this->findTrashItemId(ProductFamilyInterface::RESOURCE_KEY, $familyId);
        foreach ($restoreAttributeFirst ? [$attributeTrashItemId, $familyTrashItemId] : [$familyTrashItemId, $attributeTrashItemId] as $trashItemId) {
            $this->restoreTrashItem($trashItemId);
        }

        $family = $this->requestJson('GET', $familyUri . '?locale=en');
        $this->assertSame([['id' => $attributeId, 'required' => true, 'variantSpecific' => true]], $family['attributes']);
    }

    public function testRestoreIntoSelectedGroup(): void
    {
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $otherGroupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Technical']);
        $voltageId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'voltage', 'name' => 'Voltage', 'type' => 'text', 'group' => $otherGroupId,
        ]);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);

        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->client->request('DELETE', '/admin/api/attribute-groups/' . $groupId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->restoreTrashItem($this->findTrashItemId(AttributeInterface::RESOURCE_KEY, $attributeId), 200, ['groupUuid' => $otherGroupId]);

        $data = $this->requestJson('GET', '/admin/api/attributes/' . $attributeId . '.json?locale=en');
        $this->assertSame($otherGroupId, $data['group']);
        $voltagePosition = $this->requestJson('GET', '/admin/api/attributes/' . $voltageId . '.json?locale=en')['position'];
        $this->assertIsInt($voltagePosition);
        $this->assertSame($voltagePosition + 1, $data['position']);
    }
}
