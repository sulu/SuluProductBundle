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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(ProductResolver::class)]
class ProductResolverTest extends ProductResolverTestCase
{
    public function testIsKeyedAsProductAtTheRoot(): void
    {
        $resolver = $this->createResolver();

        self::assertSame('product', $resolver->getType());
        self::assertSame('[product]', $resolver->getOutputPath());
    }

    public function testReturnsNullForNonProductContent(): void
    {
        self::assertNull($this->createResolver()->resolve($this->createStub(DimensionContentInterface::class)));
    }

    public function testAPageCarriesTheMasterDataAndEverySection(): void
    {
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findIdentifiersBy')->willReturn(['variant-uuid-1']);

        $content = $this->resolveContent(
            $this->createContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS),
            null,
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertSame(
            ['title', 'code', 'externalIdentifier', 'productFamily', 'status', 'position', 'attributes', 'associations', 'variants'],
            \array_keys($content),
        );
    }

    /** A reference gets the always-on master data; everything expensive is opt-in. */
    public function testAReferenceCarriesTheAlwaysOnMasterDataOnly(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findIdentifiersBy');

        $content = $this->resolveContent(
            $this->createContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS),
            ['title' => 'title'],
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertSame(
            ['code', 'externalIdentifier', 'status', 'productFamily', 'position'],
            \array_keys($content),
        );
    }

    /** A property of another resolver under the same key must not drop the product's own field. */
    public function testAPropertyOfAnotherResolverDoesNotDropAnAlwaysOnField(): void
    {
        $content = $this->resolveContent(
            $this->createContent(ProductInterface::TYPE_PRODUCT),
            ['code' => 'code'],
            $this->createResolver(),
        );

        self::assertArrayHasKey('code', $content);
    }

    /** A product with variants owns no route; a simple product and a variant carry their URL. */
    public function testOnlyAProductWithVariantsHasNoUrl(): void
    {
        self::assertArrayNotHasKey('url', $this->resolveContent($this->createContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)));
        self::assertArrayHasKey('url', $this->resolveContent($this->createContent(ProductInterface::TYPE_PRODUCT)));
        self::assertArrayHasKey('url', $this->resolveContent($this->createContent(ProductInterface::TYPE_VARIANT)));
    }

    public function testAProductWithVariantsCarriesItsUrlInQueryParameterMode(): void
    {
        $content = $this->createContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $content->setRoute(new Route('products', 'parent-uuid', 'de', '/t-shirt'));

        $resolved = $this->resolveContent(
            $content,
            resolver: $this->createResolver(variantSlugResolver: $this->createVariantSlugResolver(VariantRouting::QueryParameter)),
        );

        self::assertArrayHasKey('url', $resolved);
        self::assertInstanceOf(ContentView::class, $resolved['url']);
        self::assertSame('/t-shirt', $resolved['url']->getContent());
    }

    public function testAVariantIsLinkedByItsProductUrlInQueryParameterMode(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        $content = new ProductDimensionContent($variant);
        $content->setLocale('de');
        $content->setStage('live');
        $content->setCode('T-SHIRT RED');

        $resolved = $this->resolveContent(
            $content,
            resolver: $this->createResolver(variantSlugResolver: $this->createVariantSlugResolver(VariantRouting::QueryParameter, '/t-shirt')),
        );

        self::assertInstanceOf(ContentView::class, $resolved['url']);
        self::assertSame('/t-shirt?variant=T-SHIRT%20RED', $resolved['url']->getContent());
    }

    public function testAReferenceResolvesVariantsWhenItAsksForThem(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('findIdentifiersBy')->willReturn(['variant-uuid']);

        $content = $this->resolveContent(
            $this->createContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS),
            ['variants' => 'product.variants'],
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertArrayHasKey('variants', $content);
    }

    public function testAReferenceKeepsTheKeyTheBlockAddressedItBy(): void
    {
        $content = $this->resolveContent(
            $this->createContent(ProductInterface::TYPE_PRODUCT),
            ['sku' => 'product.code'],
            $this->createResolver(),
        );

        self::assertArrayHasKey('sku', $content);
    }

    public function testAnEmptyPropertyListResolvesNothing(): void
    {
        self::assertNull(
            $this->createResolver()->resolve($this->createContent(ProductInterface::TYPE_PRODUCT), []),
        );
    }

    public function testAProductWithoutVariantsHasNoVariantsKey(): void
    {
        $content = $this->resolveContent($this->createContent(ProductInterface::TYPE_PRODUCT));

        self::assertSame(
            ['title', 'url', 'code', 'externalIdentifier', 'productFamily', 'status', 'position', 'attributes', 'associations'],
            \array_keys($content),
        );
    }

    private function createContent(string $type): ProductDimensionContent
    {
        $product = new Product();
        $product->setType($type);

        $content = new ProductDimensionContent($product);
        $content->setLocale('de');
        $content->setStage('live');

        return $content;
    }
}
