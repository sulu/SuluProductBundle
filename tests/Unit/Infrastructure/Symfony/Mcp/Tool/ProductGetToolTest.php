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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductGetTool;

#[CoversClass(ProductGetTool::class)]
final class ProductGetToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;
    private ProductGetTool $tool;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);

        $this->tool = new ProductGetTool(
            $this->productRepository->reveal(),
            $this->contentManager->reveal(),
        );
    }

    public function testGetProductReturnsNormalizedContent(): void
    {
        $product = new Product('product-uuid');
        $normalized = ['title' => 'Red Shirt', 'code' => 'SHIRT-RED', 'productFamily' => 'family-uuid'];

        $this->productRepository->getOneBy(Argument::cetera())->willReturn($product);
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn($normalized);

        $result = $this->tool->getProduct('en', 'product-uuid');

        $this->assertSame('product-uuid', $result['uuid']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame(ProductInterface::TYPE_PRODUCT, $result['type']);
        $this->assertNull($result['parent']);
        $this->assertIsArray($result['data']);
        $this->assertSame('Red Shirt', $result['data']['title']);
    }

    public function testGetProductReportsItsParentForAVariant(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $this->productRepository->getOneBy(Argument::cetera())->willReturn($variant);
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);

        $result = $this->tool->getProduct('en', 'variant-uuid');

        $this->assertSame(ProductInterface::TYPE_VARIANT, $result['type']);
        $this->assertSame('parent-uuid', $result['parent']);
    }

    public function testGetProductPassesDraftFiltersToRepository(): void
    {
        $this->productRepository->getOneBy(
            [
                'uuid' => 'my-uuid',
                'locale' => 'de',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ],
            [
                ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true,
            ],
        )->shouldBeCalledOnce()->willReturn(new Product('my-uuid'));

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);

        $this->tool->getProduct('de', 'my-uuid');
    }

    public function testGetProductReturnsErrorForMissingProduct(): void
    {
        $this->productRepository->getOneBy(Argument::cetera())
            ->willThrow(new ProductNotFoundException(['uuid' => 'missing-uuid']));

        $result = $this->tool->getProduct('en', 'missing-uuid');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('missing-uuid', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testGetProductMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductGetTool::class, 'getProduct');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'getProduct() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_get', $instance->name);
    }

    public function testGetProductReturnsAnErrorWhenLoadingFails(): void
    {
        $this->productRepository->getOneBy(Argument::cetera())->willThrow(new \RuntimeException('database gone'));

        $result = $this->tool->getProduct('en', 'product-uuid');

        $this->assertArrayNotHasKey('uuid', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('database gone', $result['error']);
        $this->assertNotEmpty($result['hint']);
    }
}
