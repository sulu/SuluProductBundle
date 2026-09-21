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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductParentContentLoader;

#[CoversClass(ProductParentContentLoader::class)]
class ProductParentContentLoaderTest extends TestCase
{
    public function testLoadsTheParentInTheVariantsLocaleAndStage(): void
    {
        $parent = new Product('parent-uuid');
        $parentContent = new ProductDimensionContent($parent);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('findOneBy')
            ->with(
                ['uuid' => 'parent-uuid', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT],
                [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
            )
            ->willReturn($parent);

        $contentAggregator = $this->createMock(ContentAggregatorInterface::class);
        $contentAggregator->expects(self::once())->method('aggregate')
            ->with($parent, ['locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])
            ->willReturn($parentContent);

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator);

        self::assertSame($parentContent, $loader->load($this->createVariantContent($parent)));
    }

    /** The enhancer and the resolver ask for the same parent while rendering one page. */
    public function testLoadsEachParentOncePerLocaleAndStage(): void
    {
        $parent = new Product('parent-uuid');

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::exactly(2))->method('findOneBy')->willReturn($parent);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturnCallback(
            static fn (ProductInterface $product): ProductDimensionContent => new ProductDimensionContent($product),
        );

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator);

        $first = $loader->load($this->createVariantContent($parent));
        self::assertSame($first, $loader->load($this->createVariantContent($parent)));

        $live = $this->createVariantContent($parent);
        $live->setStage(DimensionContentInterface::STAGE_LIVE);
        self::assertNotSame($first, $loader->load($live), 'another stage is another load');
    }

    /** The resolver swaps to the parent only for content the enhancer loaded one for. */
    public function testGetLoadedReturnsOnlyWhatWasLoadedForThatContent(): void
    {
        $parent = new Product('parent-uuid');
        $parentContent = new ProductDimensionContent($parent);

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn($parent);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturn($parentContent);

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator);

        $enhanced = $this->createVariantContent($parent);
        $loader->load($enhanced);

        self::assertSame($parentContent, $loader->getLoaded($enhanced));
        self::assertNull($loader->getLoaded($this->createVariantContent($parent)), 'same variant, but not enhanced');

        $loader->reset();
        self::assertNull($loader->getLoaded($enhanced));
    }

    public function testResetForgetsTheLoadedParents(): void
    {
        $parent = new Product('parent-uuid');

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::exactly(2))->method('findOneBy')->willReturn(null);

        $loader = new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class));
        $loader->load($this->createVariantContent($parent));
        $loader->reset();
        $loader->load($this->createVariantContent($parent));
    }

    public function testAProductWithoutParentHasNone(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findOneBy');

        $content = new ProductDimensionContent(new Product());
        $content->setLocale('en');

        $loader = new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class));

        self::assertNull($loader->load($content));
    }

    public function testAParentWithoutContentInTheLocaleIsNone(): void
    {
        $parent = new Product('parent-uuid');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn($parent);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willThrowException(new ContentNotFoundException($parent, []));

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator);

        self::assertNull($loader->load($this->createVariantContent($parent)));
    }

    private function createVariantContent(ProductInterface $parent): ProductDimensionContent
    {
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $content = new ProductDimensionContent($variant);
        $content->setLocale('en');
        $content->setStage(DimensionContentInterface::STAGE_DRAFT);

        return $content;
    }
}
