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
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductVariantListTool;

#[CoversClass(ProductVariantListTool::class)]
final class ProductVariantListToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;
    private ProductVariantListTool $tool;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);

        $this->tool = new ProductVariantListTool(
            $this->productRepository->reveal(),
            $this->contentManager->reveal(),
        );
    }

    public function testListVariantsFiltersByParent(): void
    {
        $this->productRepository->findIdentifiersBy(
            Argument::that(static fn (array $filters): bool => 'parent-uuid' === ($filters['parent'] ?? null)),
            Argument::cetera(),
        )->shouldBeCalledOnce()->willReturn(['variant-1']);
        $this->productRepository->findBy(
            Argument::that(static fn (array $filters): bool => ['variant-1'] === ($filters['uuids'] ?? null)),
            Argument::cetera(),
        )->willReturn([new Product('variant-1')]);
        $this->productRepository->countBy(Argument::cetera())->willReturn(1);

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([
            'title' => 'Red / XL',
            'attributes' => ['11' => 'red'],
        ]);

        $result = $this->tool->listProductVariants('en', 'parent-uuid');

        $this->assertSame('parent-uuid', $result['parent']);
        $this->assertSame(1, $result['total']);
        $this->assertIsArray($result['variants']);
        $variant = $result['variants'][0];
        $this->assertIsArray($variant);
        $this->assertSame('variant-1', $variant['uuid']);
        $this->assertSame(ProductInterface::RESOURCE_KEY, $variant['resourceKey']);
        $this->assertIsArray($variant['data']);
        $this->assertSame(['11' => 'red'], $variant['data']['attributes']);
    }

    public function testListVariantsReturnsAnEmptyListForAProductWithoutVariants(): void
    {
        $this->productRepository->findIdentifiersBy(Argument::cetera())->willReturn([]);
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();
        $this->productRepository->countBy(Argument::cetera())->willReturn(0);

        $result = $this->tool->listProductVariants('en', 'parent-uuid');

        $this->assertSame([], $result['variants']);
        $this->assertSame(0, $result['total']);
    }

    public function testListVariantsReturnsErrorOnFailure(): void
    {
        $this->productRepository->countBy(Argument::cetera())->willReturn(1);
        $this->productRepository->findIdentifiersBy(Argument::cetera())->willThrow(new \RuntimeException('DB gone'));

        $result = $this->tool->listProductVariants('en', 'parent-uuid');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['hint']);
    }

    public function testListProductVariantsMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductVariantListTool::class, 'listProductVariants');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listProductVariants() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_variant_list', $instance->name);
    }
}
