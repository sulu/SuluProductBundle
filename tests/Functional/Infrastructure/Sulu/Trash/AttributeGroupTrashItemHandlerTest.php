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
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Infrastructure\Sulu\Trash\AttributeGroupTrashItemHandler;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(AttributeGroupTrashItemHandler::class)]
class AttributeGroupTrashItemHandlerTest extends SuluTestCase
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
        $groupId = $this->postForId('/admin/api/attribute-groups.json?locale=en', [
            'name' => 'Physical',
            'description' => 'Physical properties',
        ]);
        $this->requestJson('PUT', '/admin/api/attribute-groups/' . $groupId . '.json?locale=de', [
            'name' => 'Physikalisch',
        ]);

        $this->client->request('DELETE', '/admin/api/attribute-groups/' . $groupId . '.json');
        $this->assertHttpStatusCode(204, $this->client->getResponse());
        $this->requestJson('GET', '/admin/api/attribute-groups/' . $groupId . '.json?locale=en', null, 404);

        $restored = $this->restoreTrashItem($this->findTrashItemId(AttributeGroupInterface::RESOURCE_KEY, $groupId));
        $this->assertRestoreNavigatesTo('sulu_product.attribute_group_trash_item_handler', $restored, $groupId);

        $data = $this->requestJson('GET', '/admin/api/attribute-groups/' . $groupId . '.json?locale=en');
        $this->assertSame('Physical', $data['name']);
        $this->assertSame('Physical properties', $data['description']);
        $this->assertSame('Physikalisch', $this->requestJson('GET', '/admin/api/attribute-groups/' . $groupId . '.json?locale=de')['name']);

        $this->assertSame(
            ['created', 'modified', 'removed', 'restored'],
            $this->findActivityTypes(AttributeGroupInterface::RESOURCE_KEY, $groupId),
        );
    }
}
