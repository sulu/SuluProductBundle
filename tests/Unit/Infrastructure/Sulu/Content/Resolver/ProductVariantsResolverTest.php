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
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Variant\ProductVariantLoader;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductVariantsResolver;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

/**
 * ProductVariantLoader is a readonly class, which the installed Prophecy version cannot double
 * (it emits an untyped property on a generated readonly subclass and PHP rejects it) — so this
 * test doubles it with PHPUnit's native mock builder instead.
 */
#[CoversClass(ProductVariantsResolver::class)]
class ProductVariantsResolverTest extends TestCase
{
    public function testReturnsNullForNonProductContent(): void
    {
        $variantLoader = $this->createMock(ProductVariantLoader::class);
        $variantLoader->expects(self::never())->method('findVariants');

        $resolver = new ProductVariantsResolver($variantLoader);
        $content = $this->createStub(DimensionContentInterface::class);

        self::assertNull($resolver->resolve($content));
    }

    public function testReturnsNullWhenResolvedAsAReference(): void
    {
        $variantLoader = $this->createMock(ProductVariantLoader::class);
        $variantLoader->expects(self::never())->method('findVariants');

        $resolver = new ProductVariantsResolver($variantLoader);

        self::assertNull($resolver->resolve($this->createVariantParentContent(), ['title' => 'title']));
    }

    public function testReturnsNullForAProductWithoutVariants(): void
    {
        $variantLoader = $this->createMock(ProductVariantLoader::class);
        $variantLoader->expects(self::never())->method('findVariants');

        $resolver = new ProductVariantsResolver($variantLoader);

        $content = new ProductDimensionContent(new Product());
        $content->setLocale('de');
        $content->setStage('draft');

        self::assertNull($resolver->resolve($content));
    }

    public function testReturnsNullWhenTheProductHasNoVariants(): void
    {
        $variantLoader = $this->createMock(ProductVariantLoader::class);
        $variantLoader->method('findVariants')->willReturn([]);

        $resolver = new ProductVariantsResolver($variantLoader);

        self::assertNull($resolver->resolve($this->createVariantParentContent()));
    }

    public function testEmitsOneResolvablePerVariant(): void
    {
        $content = $this->createVariantParentContent();

        $variantLoader = $this->createMock(ProductVariantLoader::class);
        $variantLoader->expects(self::once())
            ->method('findVariants')
            ->with(self::isInstanceOf(ProductInterface::class), 'de', 'draft')
            ->willReturn([
                $this->createVariantContent('variant-uuid-1'),
                $this->createVariantContent('variant-uuid-2'),
            ]);

        $resolver = new ProductVariantsResolver($variantLoader);
        $result = $resolver->resolve($content);

        self::assertNotNull($result);

        $resolvables = $result->getContent();
        self::assertIsArray($resolvables);
        self::assertCount(2, $resolvables);
        self::assertContainsOnlyInstancesOf(ResolvableResource::class, $resolvables);

        $first = $resolvables[0];
        self::assertSame('variant-uuid-1', $first->getId());
        self::assertSame(ProductResourceLoader::getKey(), $first->getResourceLoaderKey());
        self::assertSame(
            ['title' => 'title', 'url' => 'url', 'code' => 'code', 'image' => 'image'],
            $first->getMetadata()['properties'] ?? null,
        );
    }

    public function testAsksTheLoaderForTheContentsOwnStage(): void
    {
        $content = $this->createVariantParentContent();
        $content->setStage('draft');

        $variantLoader = $this->createMock(ProductVariantLoader::class);
        $variantLoader->expects(self::once())
            ->method('findVariants')
            ->with(self::isInstanceOf(ProductInterface::class), 'de', 'draft')
            ->willReturn([$this->createVariantContent('variant-uuid-1')]);

        $resolver = new ProductVariantsResolver($variantLoader);

        self::assertNotNull($resolver->resolve($content));
    }

    private function createVariantParentContent(): ProductDimensionContent
    {
        $product = new Product();
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $content = new ProductDimensionContent($product);
        $content->setLocale('de');
        $content->setStage('draft');

        return $content;
    }

    private function createVariantContent(string $uuid): ProductDimensionContent
    {
        $product = new Product($uuid);
        $product->setType(ProductInterface::TYPE_VARIANT);

        $content = new ProductDimensionContent($product);
        $content->setLocale('de');
        $content->setStage('draft');

        return $content;
    }
}
