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
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

#[CoversClass(ProductResolver::class)]
class ProductResolverVariantsTest extends ProductResolverTestCase
{
    public function testOmitsVariantsWhenResolvedAsAReference(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findBy');

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            ['title' => 'title'],
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertArrayNotHasKey('variants', $content);
    }

    public function testOmitsVariantsForAProductThatHasNone(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findBy');

        $product = new ProductDimensionContent(new Product());
        $product->setLocale('de');
        $product->setStage('draft');

        $content = $this->resolveContent($product, null, $this->createResolver(productRepository: $productRepository));

        self::assertArrayNotHasKey('variants', $content);
    }

    public function testOmitsVariantsWhenTheLoaderFindsNone(): void
    {
        $content = $this->resolveContent($this->createVariantParentContent());

        self::assertArrayNotHasKey('variants', $content);
    }

    public function testEmitsOneResolvablePerVariant(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())
            ->method('findBy')
            ->with(
                self::callback(static fn (array $filters): bool => 'de' === $filters['locale']
                    && DimensionContentInterface::STAGE_LIVE === $filters['stage']),
                ['position' => 'asc', 'created' => 'asc', 'uuid' => 'asc'],
            )
            ->willReturn([new Product('variant-uuid-1'), new Product('variant-uuid-2')]);

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository),
        );

        $variants = $content['variants'];
        self::assertInstanceOf(ContentView::class, $variants);

        $resolvables = $variants->getContent();
        self::assertIsArray($resolvables);
        self::assertCount(2, $resolvables);
        self::assertContainsOnlyInstancesOf(ResolvableResource::class, $resolvables);

        $first = $resolvables[0];
        self::assertSame('variant-uuid-1', $first->getId());
        self::assertSame(ProductResourceLoader::getKey(), $first->getResourceLoaderKey());
    }

    /** No projection, so a variant comes back resolved in the same shape as the page. */
    public function testCarriesNoPropertyProjection(): void
    {
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturn([new Product('variant-uuid-1')]);

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository),
        );

        $variants = $content['variants'];
        self::assertInstanceOf(ContentView::class, $variants);

        $resolvables = $variants->getContent();
        self::assertIsArray($resolvables);
        self::assertInstanceOf(ResolvableResource::class, $resolvables[0]);

        self::assertNull($resolvables[0]->getMetadata());
    }

    /** Live, so the tiles and a resolved variant cannot disagree — references only load live. */
    public function testAsksForTheLiveStageWhateverThePageIsOn(): void
    {
        $content = $this->createVariantParentContent();
        $content->setStage(DimensionContentInterface::STAGE_DRAFT);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())
            ->method('findBy')
            ->with(
                self::callback(static fn (array $filters): bool => 'de' === $filters['locale']
                    && DimensionContentInterface::STAGE_LIVE === $filters['stage']),
            )
            ->willReturn([new Product('variant-uuid-1')]);

        $this->resolveContent($content, null, $this->createResolver(productRepository: $productRepository));
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
}
