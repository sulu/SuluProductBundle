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

namespace Sulu\Product\Tests\Unit\Application\Variant;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Application\Variant\ProductVariantLoader;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductVariantLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function testReturnsNoVariantsForAProductWithoutThem(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT);

        $loader = new ProductVariantLoader(
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(ContentAggregatorInterface::class),
        );

        self::assertSame([], $loader->findVariants($product, 'de', 'live'));
    }

    public function testFindsChildrenOrderedByCreatedThenUuid(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $captured = null;
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findBy(Argument::cetera())->will(
            function(array $arguments) use (&$captured): iterable {
                $captured = $arguments;

                return [];
            },
        );

        $loader = new ProductVariantLoader(
            $repository->reveal(),
            $this->prophesize(ContentAggregatorInterface::class)->reveal(),
        );
        $loader->findVariants($product, 'de', 'live');

        self::assertIsArray($captured);
        self::assertSame(['parent' => 'parent-uuid', 'locale' => 'de', 'stage' => 'live'], $captured[0]);
        // the repository honours only `uuid` and `created` — `['id' => 'asc']` emits no ORDER BY at all
        self::assertSame(['created' => 'asc', 'uuid' => 'asc'], $captured[1]);
    }

    public function testEagerLoadsDimensionContentForTheAggregator(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $captured = null;
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findBy(Argument::cetera())->will(
            function(array $arguments) use (&$captured): iterable {
                $captured = $arguments;

                return [];
            },
        );

        $loader = new ProductVariantLoader(
            $repository->reveal(),
            $this->prophesize(ContentAggregatorInterface::class)->reveal(),
        );
        $loader->findVariants($product, 'de', 'live');

        self::assertIsArray($captured);
        self::assertSame(
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
                ],
            ],
            $captured[2],
        );
    }

    public function testSkipsAVariantWithNoContentInTheLocale(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $variant = new Product('variant-uuid');

        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findBy')->willReturn([$variant]);

        $aggregator = $this->createStub(ContentAggregatorInterface::class);
        $aggregator->method('aggregate')->willThrowException(
            new ContentNotFoundException($variant, ['locale' => 'de', 'stage' => 'live']),
        );

        $loader = new ProductVariantLoader($repository, $aggregator);

        self::assertSame([], $loader->findVariants($product, 'de', 'live'));
    }

    public function testFindsAVariantByItsCode(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $variant = new Product('variant-uuid');
        $variantContent = $this->createStub(ProductDimensionContentInterface::class);
        $variantContent->method('getCode')->willReturn('NL4FX-2');

        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findBy')->willReturn([$variant]);

        $aggregator = $this->createStub(ContentAggregatorInterface::class);
        $aggregator->method('aggregate')->willReturn($variantContent);

        $loader = new ProductVariantLoader($repository, $aggregator);

        self::assertSame($variantContent, $loader->findVariantByCode($product, 'NL4FX-2', 'de', 'live'));
    }

    public function testReturnsNullForAnUnknownCode(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $variant = new Product('variant-uuid');
        $variantContent = $this->createStub(ProductDimensionContentInterface::class);
        $variantContent->method('getCode')->willReturn('NL4FX-2');

        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findBy')->willReturn([$variant]);

        $aggregator = $this->createStub(ContentAggregatorInterface::class);
        $aggregator->method('aggregate')->willReturn($variantContent);

        $loader = new ProductVariantLoader($repository, $aggregator);

        self::assertNull($loader->findVariantByCode($product, 'nonsense', 'de', 'live'));
    }

    public function testFindsTheFirstMatchAndSkipsAVariantWithNoCode(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $noCodeVariant = new Product('no-code-uuid');
        $firstMatch = new Product('first-match-uuid');
        $secondMatch = new Product('second-match-uuid');

        $noCodeContent = $this->createStub(ProductDimensionContentInterface::class);
        $noCodeContent->method('getCode')->willReturn(null);

        $firstMatchContent = $this->createStub(ProductDimensionContentInterface::class);
        $firstMatchContent->method('getCode')->willReturn('NL4FX-2');

        $secondMatchContent = $this->createStub(ProductDimensionContentInterface::class);
        $secondMatchContent->method('getCode')->willReturn('NL4FX-2');

        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findBy')->willReturn([$noCodeVariant, $firstMatch, $secondMatch]);

        $aggregator = $this->createStub(ContentAggregatorInterface::class);
        $aggregator->method('aggregate')->willReturnMap([
            [$noCodeVariant, ['locale' => 'de', 'stage' => 'live'], $noCodeContent],
            [$firstMatch, ['locale' => 'de', 'stage' => 'live'], $firstMatchContent],
            [$secondMatch, ['locale' => 'de', 'stage' => 'live'], $secondMatchContent],
        ]);

        $loader = new ProductVariantLoader($repository, $aggregator);

        self::assertSame($firstMatchContent, $loader->findVariantByCode($product, 'NL4FX-2', 'de', 'live'));
    }
}
