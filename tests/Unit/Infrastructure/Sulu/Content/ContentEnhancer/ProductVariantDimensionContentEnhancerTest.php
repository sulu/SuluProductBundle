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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\ContentEnhancer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ContentEnhancer\ProductVariantDimensionContentEnhancer;
use Sulu\Product\Infrastructure\Sulu\Content\ProductParentContentLoader;

#[CoversClass(ProductVariantDimensionContentEnhancer::class)]
class ProductVariantDimensionContentEnhancerTest extends TestCase
{
    public function testAVariantTakesItsParentsContentTabAndKeepsItsOwnTitle(): void
    {
        $parent = new Product('parent-uuid');
        $parentContent = new ProductDimensionContent($parent);
        $parentContent->setTemplateKey('product');
        $parentContent->setTemplateData(['title' => 'NC3FX', 'blocks' => ['parent block'], 'headerTitle' => 'Header']);
        $parentContent->setExcerptData(['title' => 'Parent excerpt']);
        $parentContent->setSeoData(['title' => 'Parent SEO']);
        $parentContent->setSeoNoIndex(true);

        $variantContent = $this->createMergedVariantContent($parent);
        $variantContent->setTitle('NC3FX-B');
        $variantContent->setTemplateData(['title' => 'NC3FX-B', 'blocks' => ['copied block']]);
        $variantContent->setExcerptData(['title' => 'Copied excerpt']);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('findOneBy')
            ->with(['uuid' => 'parent-uuid', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])
            ->willReturn($parent);

        $contentAggregator = $this->createMock(ContentAggregatorInterface::class);
        $contentAggregator->expects(self::once())->method('aggregate')
            ->with($parent, ['locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])
            ->willReturn($parentContent);

        $enhanced = (new ProductVariantDimensionContentEnhancer(new ProductParentContentLoader($productRepository, $contentAggregator)))->enhance($variantContent);

        self::assertSame($variantContent, $enhanced);
        self::assertSame('NC3FX-B', $variantContent->getTitle(), 'the variant keeps its title for product.currentVariant.title');
        self::assertSame('product', $variantContent->getTemplateKey());
        self::assertSame(['title' => 'NC3FX', 'blocks' => ['parent block'], 'headerTitle' => 'Header'], $variantContent->getTemplateData());
        self::assertSame('Parent excerpt', $variantContent->getExcerptTitle());
        self::assertSame('Parent SEO', $variantContent->getSeoTitle());
        self::assertTrue($variantContent->getSeoNoIndex());
    }

    public function testContentOfAnotherResourceIsLeftAlone(): void
    {
        $content = $this->createStub(DimensionContentInterface::class);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findOneBy');

        $enhanced = (new ProductVariantDimensionContentEnhancer(new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class))))
            ->enhance($content);

        self::assertSame($content, $enhanced);
    }

    public function testAProductWithoutParentIsLeftAlone(): void
    {
        $content = new ProductDimensionContent(new Product());
        $content->setLocale('en');
        $content->markAsMerged();
        $content->setTemplateData(['title' => 'CQ2M']);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findOneBy');

        (new ProductVariantDimensionContentEnhancer(new ProductParentContentLoader($productRepository, $this->createStub(ContentAggregatorInterface::class))))
            ->enhance($content);

        self::assertSame(['title' => 'CQ2M'], $content->getTemplateData());
    }

    public function testAVariantWhoseParentHasNoContentKeepsItsOwn(): void
    {
        $parent = new Product('parent-uuid');
        $variantContent = $this->createMergedVariantContent($parent);
        $variantContent->setTemplateData(['title' => 'NC3FX-B', 'blocks' => ['own block']]);

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn($parent);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willThrowException(new ContentNotFoundException($parent, []));

        (new ProductVariantDimensionContentEnhancer(new ProductParentContentLoader($productRepository, $contentAggregator)))->enhance($variantContent);

        self::assertSame(['title' => 'NC3FX-B', 'blocks' => ['own block']], $variantContent->getTemplateData());
    }

    private function createMergedVariantContent(ProductInterface $parent): ProductDimensionContent
    {
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $content = new ProductDimensionContent($variant);
        $content->setLocale('en');
        $content->setStage(DimensionContentInterface::STAGE_DRAFT);
        $content->markAsMerged();

        return $content;
    }
}
