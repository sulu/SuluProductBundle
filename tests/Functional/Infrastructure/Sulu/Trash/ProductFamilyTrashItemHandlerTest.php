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
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Tests\Functional\Traits\CreateMediaTrait;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Trash\ProductFamilyTrashItemHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductFamilyTrashItemHandler::class)]
class ProductFamilyTrashItemHandlerTest extends SuluTestCase
{
    use CreateMediaTrait;
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
        $media = self::createMedia(self::createCollection());
        self::getEntityManager()->flush();
        $mediaId = $media->getId();

        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', ['name' => 'Physical']);
        $attributeId = $this->postForId('/admin/api/attributes.json?locale=en', [
            'key' => 'color', 'name' => 'Color', 'type' => 'text', 'group' => $groupId,
        ]);
        $familyId = $this->postForId('/admin/api/product-families.json?locale=en', [
            'key' => 'apparel',
            'name' => 'Apparel',
            'description' => 'Clothing',
            'image' => ['id' => $mediaId],
            'attributes' => [['id' => $attributeId, 'required' => true, 'variantSpecific' => false]],
        ]);
        $this->requestJson('PUT', '/admin/api/product-families/' . $familyId . '.json?locale=de', [
            'name' => 'Bekleidung',
            'image' => ['id' => $mediaId],
            'attributes' => [['id' => $attributeId, 'required' => true, 'variantSpecific' => false]],
        ]);

        self::getEntityManager()->createQueryBuilder()
            ->update(ProductFamily::class, 'entity')
            ->set('entity.externalIdentifier', ':externalIdentifier')
            ->set('entity.created', ':created')
            ->where('entity.uuid = :uuid')
            ->setParameter('externalIdentifier', 'erp-1')
            ->setParameter('created', new \DateTimeImmutable('2020-01-02 03:04:05'), Types::DATETIME_IMMUTABLE)
            ->setParameter('uuid', $familyId)
            ->getQuery()
            ->execute();

        $this->client->request('DELETE', '/admin/api/product-families/' . $familyId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());
        $this->requestJson('GET', '/admin/api/product-families/' . $familyId . '.json?locale=en', null, 404);

        $restored = $this->restoreTrashItem($this->findTrashItemId(ProductFamilyInterface::RESOURCE_KEY, $familyId));
        $this->assertRestoreNavigatesTo('sulu_product.product_family_trash_item_handler', $restored, $familyId);

        $data = $this->requestJson('GET', '/admin/api/product-families/' . $familyId . '.json?locale=en');
        $this->assertSame('apparel', $data['key']);
        $this->assertSame('Apparel', $data['name']);
        $this->assertSame('Clothing', $data['description']);
        $this->assertSame('erp-1', $data['externalIdentifier']);
        $restoredEntity = $this->getClearedEntityManager()->find(ProductFamilyInterface::class, $familyId);
        $this->assertNotNull($restoredEntity);
        $this->assertSame('2020-01-02 03:04:05', $restoredEntity->getCreated()->format('Y-m-d H:i:s'));
        $this->assertSame(['id' => $mediaId], $data['image']);
        $this->assertSame([['id' => $attributeId, 'required' => true, 'variantSpecific' => false]], $data['attributes']);
        $this->assertSame('Bekleidung', $this->requestJson('GET', '/admin/api/product-families/' . $familyId . '.json?locale=de')['name']);

        $this->assertSame(
            ['created', 'modified', 'removed', 'restored'],
            $this->findActivityTypes(ProductFamilyInterface::RESOURCE_KEY, $familyId),
        );
    }

    public function testRestoreFailsWhenKeyIsTaken(): void
    {
        $familyId = $this->postForId('/admin/api/product-families.json?locale=en', ['key' => 'apparel', 'name' => 'Apparel']);

        $this->client->request('DELETE', '/admin/api/product-families/' . $familyId . '.json');
        $this->requestJson('POST', '/admin/api/product-families.json?locale=en', ['key' => 'apparel', 'name' => 'Clothing'], 201);

        $this->restoreTrashItem($this->findTrashItemId(ProductFamilyInterface::RESOURCE_KEY, $familyId), 409);
    }

    public function testRestoreSkipsLinkToRemovedAttribute(): void
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

        $this->client->request('DELETE', '/admin/api/product-families/' . $familyId . '.json');
        $this->client->request('DELETE', '/admin/api/attributes/' . $attributeId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->restoreTrashItem($this->findTrashItemId(ProductFamilyInterface::RESOURCE_KEY, $familyId));

        $this->assertSame([], $this->requestJson('GET', '/admin/api/product-families/' . $familyId . '.json?locale=en')['attributes']);
    }
}
