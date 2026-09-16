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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
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

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator, $this->createStub(EntityManagerInterface::class));

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

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator, $this->createStub(EntityManagerInterface::class));

        $first = $loader->load($this->createVariantContent($parent));
        self::assertSame($first, $loader->load($this->createVariantContent($parent)));

        $live = $this->createVariantContent($parent);
        $live->setStage(DimensionContentInterface::STAGE_LIVE);
        self::assertNotSame($first, $loader->load($live), 'another stage is another load');
    }

    public function testResetForgetsTheLoadedParents(): void
    {
        $parent = new Product('parent-uuid');

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::exactly(2))->method('findOneBy')->willReturn(null);

        $loader = new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class), $this->createStub(EntityManagerInterface::class));
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

        $loader = new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class), $this->createStub(EntityManagerInterface::class));

        self::assertNull($loader->load($content));
    }

    public function testAParentWithoutContentInTheLocaleIsNone(): void
    {
        $parent = new Product('parent-uuid');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn($parent);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willThrowException(new ContentNotFoundException($parent, []));

        $loader = new ProductParentContentLoader($productRepository, $contentAggregator, $this->createStub(EntityManagerInterface::class));

        self::assertNull($loader->load($this->createVariantContent($parent)));
    }

    /** A fetch join does not replace dimension contents an earlier query left on a managed parent. */
    public function testRefreshesAParentWhoseDimensionContentsAreAlreadyLoaded(): void
    {
        $parent = $this->createParentWithDimensionContents(initialized: true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('refresh')->with($parent);

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn(null);

        $loader = new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class), $entityManager);
        $loader->load($this->createVariantContent($parent));
    }

    public function testLeavesAParentWhoseDimensionContentsAreNotLoaded(): void
    {
        $parent = $this->createParentWithDimensionContents(initialized: false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('refresh');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn(null);

        $loader = new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class), $entityManager);
        $loader->load($this->createVariantContent($parent));
    }

    private function createParentWithDimensionContents(bool $initialized): Product
    {
        $parent = new Product('parent-uuid');

        $dimensionContents = new PersistentCollection(
            $this->createStub(EntityManagerInterface::class),
            new ClassMetadata(ProductDimensionContent::class),
            new ArrayCollection(),
        );
        $dimensionContents->setInitialized($initialized);

        (new \ReflectionProperty(Product::class, 'dimensionContents'))->setValue($parent, $dimensionContents);

        return $parent;
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
