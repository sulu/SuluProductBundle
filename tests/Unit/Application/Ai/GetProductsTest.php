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

namespace Sulu\Product\Tests\Unit\Application\Ai;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Ai\GetProducts;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(GetProducts::class)]
class GetProductsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private GetProducts $getProducts;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->getProducts = new GetProducts($this->productRepository->reveal());
    }

    public function testInvokeWithoutQueryOrFamilyReturnsNoMatchWithoutQuerying(): void
    {
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = ($this->getProducts)('en');

        $this->assertSame('no_match', $result['status']);
        $this->assertSame([], $result['results']);
        $this->assertNotNull($result['instruction']);
    }

    public function testInvokeWithBlankQueryAndFamilyReturnsNoMatchWithoutQuerying(): void
    {
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = ($this->getProducts)('en', '   ', '  ');

        $this->assertSame('no_match', $result['status']);
    }

    public function testInvokeWithQueryPassesQueryFilterAndExcludesVariantsByDefault(): void
    {
        $this->productRepository->findBy(
            Argument::that(static function(array $filters): bool {
                return 'en' === $filters['locale']
                    && DimensionContentInterface::STAGE_LIVE === $filters['stage']
                    && 'ABC' === $filters['query']
                    && !isset($filters['productFamilyName'])
                    && [ProductInterface::TYPE_VARIANT] === $filters['excludeTypes']
                    && 10 === $filters['limit'];
            }),
            ['title' => 'asc'],
        )->willReturn([])->shouldBeCalled();

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('no_match', $result['status']);
    }

    public function testInvokeWithIncludeVariantsTrueOmitsExcludeTypes(): void
    {
        $this->productRepository->findBy(
            Argument::that(static fn (array $filters): bool => !isset($filters['excludeTypes'])),
            Argument::any(),
        )->willReturn([])->shouldBeCalled();

        ($this->getProducts)('en', 'ABC', null, true);
    }

    public function testInvokeCapsLimitAtTwentyFive(): void
    {
        $this->productRepository->findBy(
            Argument::that(static fn (array $filters): bool => 25 === $filters['limit']),
            Argument::any(),
        )->willReturn([])->shouldBeCalled();

        ($this->getProducts)('en', 'ABC', null, false, 999);
    }

    public function testInvokeWithProductFamilyOnlyPassesProductFamilyNameFilter(): void
    {
        $this->productRepository->findBy(
            Argument::that(static function(array $filters): bool {
                return !isset($filters['query']) && 'Fasteners' === $filters['productFamilyName'];
            }),
            Argument::any(),
        )->willReturn([])->shouldBeCalled();

        ($this->getProducts)('en', null, 'Fasteners');
    }

    public function testInvokeReturnsMatchedProductWithFamilyAndUrl(): void
    {
        $product = $this->buildMatchedProduct('en');

        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('ok', $result['status']);
        $this->assertNull($result['instruction']);
        $this->assertSame([
            'code' => 'ABC-1',
            'title' => 'Widget',
            'productFamily' => 'Fasteners',
            'url' => 'widget',
        ], $result['results'][0]);
    }

    public function testInvokeSkipsProductMissingLiveLocalizedContent(): void
    {
        $product = new Product();
        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-2');
        $product->addDimensionContent($unlocalized);

        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('no_match', $result['status']);
        $this->assertSame([], $result['results']);
    }

    public function testInvokeSkipsProductMissingUnlocalizedContent(): void
    {
        $product = new Product();
        $localized = $product->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $product->addDimensionContent($localized);

        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('no_match', $result['status']);
    }

    public function testInvokeSkipsDraftDimensionContent(): void
    {
        $product = new Product();

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-3');
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_DRAFT);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $product->addDimensionContent($localized);

        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('no_match', $result['status']);
    }

    public function testInvokeSkipsProductWithLiveDimensionContentButNoTitle(): void
    {
        $product = new Product();

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-5');
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        // title left null on purpose
        $product->addDimensionContent($localized);

        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('no_match', $result['status']);
    }

    public function testInvokeReturnsNullFamilyAndUrlWhenAbsent(): void
    {
        $product = new Product();

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-4');
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $product->addDimensionContent($localized);

        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->getProducts)('en', 'ABC');

        $this->assertSame('ok', $result['status']);
        $this->assertNull($result['results'][0]['productFamily']);
        $this->assertNull($result['results'][0]['url']);
    }

    private function buildMatchedProduct(string $locale): Product
    {
        $family = new ProductFamily();
        $family->addTranslation(new ProductFamilyTranslation($family, $locale, 'Fasteners'));

        $product = new Product();

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-1');
        $unlocalized->setProductFamily($family);
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale($locale);
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $localized->setRoute(new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), $locale, 'widget'));
        $product->addDimensionContent($localized);

        return $product;
    }
}
