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

namespace Sulu\Product\Tests\Functional\Infrastructure\Symfony\Mcp;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductGetTool;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductUpdateTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Runs the update and get tools against the real kernel and database. The unit tests stub the
 * repository, so only this shows that a locale without content can be created and that a
 * product can be linked by code.
 *
 * Runs in separate processes: this kernel and the default one both declare the webspace cache class.
 */
#[RunTestsInSeparateProcesses]
class ProductToolsIntegrationTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            ['environment' => 'test_mcp_write'],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testUpdateCreatesALocaleTheProductHasNoContentIn(): void
    {
        $uuid = $this->createProduct($this->createFamily(), 'Shirt', 'SHIRT-1');

        $result = $this->updateTool()->updateProduct($uuid, 'de', title: 'Hemd');

        $this->assertTrue($result['success'] ?? false, \json_encode($result) ?: '');
        $de = $this->dataOf('de', $uuid);
        $this->assertSame('Hemd', $de['title'] ?? null);
        $this->assertSame('SHIRT-1', $de['code'] ?? null);
        $this->assertSame('Shirt', $this->dataOf('en', $uuid)['title'] ?? null);
    }

    public function testGetAnswersAHintForALocaleWithoutContent(): void
    {
        $uuid = $this->createProduct($this->createFamily(), 'Shirt', 'SHIRT-2');

        $result = $this->getTool()->getProduct('fr', $uuid);

        $this->assertSame([], $result['data'] ?? null);
        $hint = $result['hint'] ?? null;
        $this->assertIsString($hint);
        $this->assertStringContainsString('sulu_product_update', $hint);
    }

    public function testUpdateLinksAProductByCodeThatHasNoContentInTheLocale(): void
    {
        $family = $this->createFamily();
        $shirt = $this->createProduct($family, 'Shirt', 'SHIRT-3');
        $this->createProduct($family, 'Belt', 'BELT-3');

        $result = $this->updateTool()->updateProduct($shirt, 'de', title: 'Hemd', associations: ['alternative' => ['BELT-3']]);

        $this->assertTrue($result['success'] ?? false, \json_encode($result) ?: '');
        $linked = $this->associationsOf('de', $shirt)['alternative'] ?? null;
        $this->assertIsArray($linked);
        $this->assertCount(1, $linked);
    }

    public function testUpdateKeepsTheAssociationTypesItDoesNotPass(): void
    {
        $family = $this->createFamily();
        $shirt = $this->createProduct($family, 'Shirt', 'SHIRT-5');
        $belt = $this->createProduct($family, 'Belt', 'BELT-5');
        $this->updateTool()->updateProduct($shirt, 'en', associations: ['suitable' => [$belt]]);

        $result = $this->updateTool()->updateProduct($shirt, 'de', title: 'Hemd', associations: ['alternative' => [$belt]]);

        $this->assertTrue($result['success'] ?? false, \json_encode($result) ?: '');
        $associations = $this->associationsOf('de', $shirt);
        $this->assertSame([$belt], $associations['suitable'] ?? null);
        $this->assertSame([$belt], $associations['alternative'] ?? null);
    }

    public function testUpdateRejectsAnUnknownAssociationTarget(): void
    {
        $uuid = $this->createProduct($this->createFamily(), 'Shirt', 'SHIRT-4');

        $result = $this->updateTool()->updateProduct($uuid, 'en', associations: ['alternative' => ['NOPE']]);

        $error = $result['error'] ?? null;
        $this->assertIsString($error);
        $this->assertStringContainsString('NOPE', $error);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataOf(string $locale, string $uuid): array
    {
        $data = $this->getTool()->getProduct($locale, $uuid)['data'] ?? null;
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function associationsOf(string $locale, string $uuid): array
    {
        $associations = $this->dataOf($locale, $uuid)['associations'] ?? null;
        $this->assertIsArray($associations);

        /** @var array<string, mixed> $associations */
        return $associations;
    }

    private function updateTool(): ProductUpdateTool
    {
        $tool = static::getContainer()->get('test.sulu_product.mcp_product_update_tool');
        $this->assertInstanceOf(ProductUpdateTool::class, $tool);

        return $tool;
    }

    private function getTool(): ProductGetTool
    {
        $tool = static::getContainer()->get('test.sulu_product.mcp_product_get_tool');
        $this->assertInstanceOf(ProductGetTool::class, $tool);

        return $tool;
    }

    private function createFamily(): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode(['locale' => 'en', 'key' => 'family', 'name' => 'Family', 'description' => null]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsString($data['id']);

        return $data['id'];
    }

    private function createProduct(string $familyId, string $title, string $code): string
    {
        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => $title,
            'code' => $code,
            'url' => '/' . \strtolower($code),
            'productFamily' => $familyId,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsString($data['id']);

        return $data['id'];
    }
}
