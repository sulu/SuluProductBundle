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

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductCreateTool;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductVariantCreateTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductCreateTool::class)]
#[CoversClass(ProductVariantCreateTool::class)]
class ProductCreateToolUrlTest extends SuluTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            ['environment' => 'test_mcp_write'],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testCreateWithoutAUrlStoresTheRouteFromTheTitle(): void
    {
        $title = 'Monstera ' . $this->uniqueSuffix();

        $uuid = $this->createProduct($title);

        $this->assertSame('/products/' . \strtolower(\str_replace(' ', '-', $title)), $this->getProductUrl($uuid));
    }

    public function testCreateWithAUrlKeepsIt(): void
    {
        $url = '/shop/ficus-' . $this->uniqueSuffix();

        $uuid = $this->createProduct('Ficus', ['url' => $url]);

        $this->assertSame($url, $this->getProductUrl($uuid));
    }

    public function testCreateTwoProductsWithTheSameTitleKeepsTheUrlsUnique(): void
    {
        $title = 'Calathea ' . $this->uniqueSuffix();
        $familyId = $this->createProductFamily();

        $first = $this->createProduct($title, null, $familyId);
        $second = $this->createProduct($title, null, $familyId);

        $this->assertSame($this->getProductUrl($first) . '-1', $this->getProductUrl($second));
    }

    public function testProductWithVariantsHasNoUrlAndItsVariantGetsOne(): void
    {
        $title = 'Pelargonium ' . $this->uniqueSuffix();
        $result = $this->tool()->createProduct('en', $this->createProductFamily(), $title, type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->assertTrue($result['success'] ?? false, \json_encode($result) ?: '');
        $this->assertIsString($result['uuid']);

        $variant = $this->variantTool()->createProductVariant('en', $result['uuid'], 'Red ' . $title);

        $this->assertTrue($variant['success'] ?? false, \json_encode($variant) ?: '');
        $this->assertIsString($variant['uuid']);
        $this->assertNull($this->getProductData($result['uuid'])['url'] ?? null);
        $this->assertSame('/products/red-' . \strtolower(\str_replace(' ', '-', $title)), $this->getProductUrl($variant['uuid']));
    }

    public function testCreateVariantWithAUrlKeepsIt(): void
    {
        $result = $this->tool()->createProduct('en', $this->createProductFamily(), 'Begonia ' . $this->uniqueSuffix(), type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->assertIsString($result['uuid']);
        $url = '/shop/begonia-' . $this->uniqueSuffix();

        $variant = $this->variantTool()->createProductVariant('en', $result['uuid'], 'Pink', url: $url);

        $this->assertIsString($variant['uuid']);
        $this->assertSame($url, $this->getProductUrl($variant['uuid']));
    }

    private function variantTool(): ProductVariantCreateTool
    {
        $tool = self::getContainer()->get('test.sulu_product.product_variant_create_tool');
        $this->assertInstanceOf(ProductVariantCreateTool::class, $tool);

        return $tool;
    }

    private function uniqueSuffix(): string
    {
        return \bin2hex(\random_bytes(4));
    }

    private function tool(): ProductCreateTool
    {
        $tool = self::getContainer()->get('test.sulu_product.product_create_tool');
        $this->assertInstanceOf(ProductCreateTool::class, $tool);

        return $tool;
    }

    /**
     * @param array<string, mixed>|null $content
     */
    private function createProduct(string $title, ?array $content = null, ?string $familyId = null): string
    {
        $result = $this->tool()->createProduct('en', $familyId ?? $this->createProductFamily(), $title, content: $content);

        $this->assertTrue($result['success'] ?? false, \json_encode($result) ?: '');
        $this->assertIsString($result['uuid']);

        return $result['uuid'];
    }

    private function createProductFamily(): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Url Family',
            'description' => null,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsString($data['id']);

        return $data['id'];
    }

    private function getProductUrl(string $uuid): string
    {
        $url = $this->getProductData($uuid)['url'] ?? null;
        $this->assertIsString($url);

        return $url;
    }

    /**
     * @return array<mixed>
     */
    private function getProductData(string $uuid): array
    {
        $this->client->request('GET', '/admin/api/products/' . $uuid . '.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        return $data;
    }
}
