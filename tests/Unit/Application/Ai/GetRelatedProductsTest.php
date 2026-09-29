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
use Sulu\Product\Application\Ai\GetRelatedProducts;
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(GetRelatedProducts::class)]
class GetRelatedProductsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private ProductAssociationTypeRegistry $associationTypeRegistry;

    private GetRelatedProducts $getRelatedProducts;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->associationTypeRegistry = new ProductAssociationTypeRegistry([
            'accessory' => ['label' => 'Accessories'],
            'alternative' => ['label' => 'Alternatives'],
        ]);
        $this->getRelatedProducts = new GetRelatedProducts(
            $this->productRepository->reveal(),
            $this->associationTypeRegistry,
            new ProductUrlGenerator(new FakeRouteGenerator()),
        );
    }

    public function testInvokeThrowsWhenProductNotFound(): void
    {
        $this->productRepository->getOneBy([
            'code' => 'MISSING',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willThrow(new ProductNotFoundException(['code' => 'MISSING']));

        $this->expectException(\InvalidArgumentException::class);

        ($this->getRelatedProducts)('MISSING', 'en');
    }

    public function testInvokeReturnsNoVariantsForProductWithoutVariantType(): void
    {
        $product = $this->buildProduct('en', 'ABC-1', ProductInterface::TYPE_PRODUCT);

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = ($this->getRelatedProducts)('ABC-1', 'en');

        $this->assertSame([], $result['variants']);
    }

    public function testInvokeReturnsVariantsForProductWithVariantsType(): void
    {
        $product = $this->buildProduct('en', 'ABC-1', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variant = $this->buildProduct('en', 'ABC-1-RED', ProductInterface::TYPE_VARIANT);

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $this->productRepository->findBy(
            ['parent' => $product->getUuid(), 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE],
            ['position' => 'asc'],
        )->willReturn([$variant]);

        $result = ($this->getRelatedProducts)('ABC-1', 'en');

        $this->assertSame([
            ['code' => 'ABC-1-RED', 'title' => 'ABC-1-RED Title', 'url' => 'https://example.org/en/abc-1-red'],
        ], $result['variants']);
    }

    public function testInvokeReturnsAssociationsGroupedByType(): void
    {
        $product = $this->buildProduct('en', 'ABC-1', ProductInterface::TYPE_PRODUCT);
        $accessory = $this->buildProduct('en', 'ACC-1', ProductInterface::TYPE_PRODUCT);

        $unlocalized = $this->unlocalizedContent($product);
        $unlocalized->addAssociation(new ProductAssociation($unlocalized, $accessory, 'accessory'));

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $result = ($this->getRelatedProducts)('ABC-1', 'en');

        $this->assertSame([
            'accessory' => [
                ['code' => 'ACC-1', 'title' => 'ACC-1 Title', 'url' => 'https://example.org/en/acc-1'],
            ],
        ], $result['associations']);
    }

    public function testInvokeOmitsAssociationTypesWithoutTargets(): void
    {
        $product = $this->buildProduct('en', 'ABC-1', ProductInterface::TYPE_PRODUCT);

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $result = ($this->getRelatedProducts)('ABC-1', 'en');

        $this->assertSame([], $result['associations']);
    }

    public function testInvokeReturnsNoAssociationsWhenUnlocalizedContentMissing(): void
    {
        $product = new Product();

        $localized = $product->createDimensionContent();
        $localized->setLocale('en');
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $product->addDimensionContent($localized);

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $result = ($this->getRelatedProducts)('ABC-1', 'en');

        $this->assertSame([], $result['associations']);
    }

    private function buildProduct(string $locale, string $code, string $type): Product
    {
        $product = new Product();
        $product->setType($type);

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode($code);
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale($locale);
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle($code . ' Title');
        $localized->setRoute(new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), $locale, \strtolower($code)));
        $product->addDimensionContent($localized);

        return $product;
    }

    private function unlocalizedContent(Product $product): ProductDimensionContentInterface
    {
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if (DimensionContentInterface::STAGE_LIVE === $dimensionContent->getStage()
                && DimensionContentInterface::CURRENT_VERSION === $dimensionContent->getVersion()
                && null === $dimensionContent->getLocale()
            ) {
                return $dimensionContent;
            }
        }

        throw new \RuntimeException('Unlocalized dimension content not found.');
    }
}
