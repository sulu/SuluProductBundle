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
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductListTool;

#[CoversClass(ProductListTool::class)]
final class ProductListToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;
    private ProductListTool $tool;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);

        $this->tool = new ProductListTool(
            $this->productRepository->reveal(),
            $this->contentManager->reveal(),
        );
    }

    public function testListProductsReturnsSummaries(): void
    {
        $this->productRepository->findIdentifiersBy(Argument::cetera())->willReturn(['uuid-1']);
        $this->productRepository->findBy(Argument::cetera())->willReturn([new Product('uuid-1')]);
        $this->productRepository->countBy(Argument::cetera())->willReturn(1);
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([
            'title' => 'Shirt',
            'code' => 'SHIRT-1',
            'status' => 'available',
            'blocks' => 'not a summary field',
        ]);

        $result = $this->tool->listProducts('en');

        $this->assertSame(1, $result['total']);
        $this->assertIsArray($result['products']);
        $this->assertCount(1, $result['products']);
        $product = $result['products'][0];
        $this->assertIsArray($product);
        $this->assertSame('uuid-1', $product['uuid']);
        $this->assertIsArray($product['data']);
        $this->assertSame('Shirt', $product['data']['title']);
        $this->assertArrayNotHasKey('blocks', $product['data']);
    }

    public function testListProductsExcludesVariantsByDefault(): void
    {
        $this->productRepository->findIdentifiersBy(
            Argument::that(static fn (array $filters): bool => [ProductInterface::TYPE_VARIANT] === ($filters['excludeTypes'] ?? null)),
            Argument::cetera(),
        )->shouldBeCalledOnce()->willReturn([]);
        $this->productRepository->countBy(Argument::cetera())->willReturn(0);

        $this->tool->listProducts('en');
    }

    public function testListProductsCanIncludeVariants(): void
    {
        $this->productRepository->findIdentifiersBy(
            Argument::that(static fn (array $filters): bool => !isset($filters['excludeTypes'])),
            Argument::cetera(),
        )->shouldBeCalledOnce()->willReturn([]);
        $this->productRepository->countBy(Argument::cetera())->willReturn(0);

        $this->tool->listProducts('en', includeVariants: true);
    }

    public function testListProductsFiltersByExplicitType(): void
    {
        $this->productRepository->findIdentifiersBy(
            Argument::that(static fn (array $filters): bool => [ProductInterface::TYPE_VARIANT] === ($filters['types'] ?? null)
                && !isset($filters['excludeTypes'])),
            Argument::cetera(),
        )->shouldBeCalledOnce()->willReturn([]);
        $this->productRepository->countBy(Argument::cetera())->willReturn(0);

        $this->tool->listProducts('en', type: ProductInterface::TYPE_VARIANT);
    }

    public function testListProductsRejectsAnUnsupportedSortField(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // The product repository sorts on uuid/created plus the dimension-content fields;
        // "id" is advertised by its phpdoc but silently ignored.
        $this->tool->listProducts('en', sortBy: 'id');
    }

    public function testListProductsResolvesThePageBeforeLoadingSoTheLimitCountsProducts(): void
    {
        $this->productRepository->countBy(Argument::cetera())->willReturn(6);

        $this->productRepository->findIdentifiersBy(
            Argument::that(static fn (array $filters): bool => 3 === ($filters['limit'] ?? null)),
            Argument::cetera(),
        )->shouldBeCalledOnce()->willReturn(['a', 'b', 'c']);

        $this->productRepository->findBy(
            Argument::that(static fn (array $filters): bool => ['a', 'b', 'c'] === ($filters['uuids'] ?? null)
                && !isset($filters['limit'], $filters['page'])),
            Argument::cetera(),
        )->shouldBeCalledOnce()->willReturn([new Product('a'), new Product('b'), new Product('c')]);

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn(['title' => 'x']);

        $result = $this->tool->listProducts('en', limit: 3);

        self::assertIsArray($result['products']);
        self::assertCount(
            3,
            $result['products'],
            'limit must bound products, not the fetch-joined dimension-content rows.',
        );
        self::assertSame(6, $result['total']);
    }

    public function testListProductsReturnsErrorOnFailure(): void
    {
        $this->productRepository->countBy(Argument::cetera())->willReturn(1);
        $this->productRepository->findIdentifiersBy(Argument::cetera())->willThrow(new \RuntimeException('DB gone'));

        $result = $this->tool->listProducts('en');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['hint']);
    }

    public function testListProductsMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductListTool::class, 'listProducts');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listProducts() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_list', $instance->name);
    }
}
