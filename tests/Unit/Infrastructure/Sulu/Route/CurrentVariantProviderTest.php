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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Route\CurrentVariantProvider;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CurrentVariantProvider::class)]
class CurrentVariantProviderTest extends TestCase
{
    public function testReturnsTheVariantOfTheRenderedProduct(): void
    {
        $parentContent = new ProductDimensionContent(new Product('parent-uuid'));
        $variantContent = $this->createVariantContent($parentContent->getResource());

        $provider = $this->createProvider(new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $variantContent]));

        self::assertSame($variantContent, $provider->getCurrentVariant($parentContent));
    }

    public function testIgnoresAVariantOfAnotherProduct(): void
    {
        $variantContent = $this->createVariantContent(new Product('other-parent-uuid'));

        $provider = $this->createProvider(new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $variantContent]));

        self::assertNull($provider->getCurrentVariant(new ProductDimensionContent(new Product('parent-uuid'))));
    }

    public function testARequestWithoutVariantHasNone(): void
    {
        $provider = $this->createProvider(new Request());

        self::assertNull($provider->getCurrentVariant(new ProductDimensionContent(new Product('parent-uuid'))));
    }

    public function testNoRequestHasNoVariant(): void
    {
        $provider = new CurrentVariantProvider(
            new RequestStack(),
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(ContentAggregatorInterface::class),
            VariantRouting::Route,
        );

        self::assertNull($provider->getCurrentVariant(new ProductDimensionContent(new Product('parent-uuid'))));
    }

    public function testRouteModeIgnoresTheQueryParameter(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findBy');

        $provider = $this->createProvider(new Request(query: ['variant' => 'RED']), productRepository: $productRepository);

        self::assertNull($provider->getCurrentVariant($this->createProductContent()));
    }

    public function testLoadsThePublishedVariantNamedByTheQueryParameter(): void
    {
        $productContent = $this->createProductContent();
        $variant = new Product('variant-uuid');
        $variant->setParent($productContent->getResource());
        $variantContent = new ProductDimensionContent($variant);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('findBy')->with(
            ['parent' => 'parent-uuid', 'code' => 'RED', 'locale' => 'en', 'stage' => 'live'],
            [],
            self::anything(),
        )->willReturn([$variant]);

        $contentAggregator = $this->createMock(ContentAggregatorInterface::class);
        $contentAggregator->expects(self::once())->method('aggregate')
            ->with($variant, ['locale' => 'en', 'stage' => 'live', 'version' => 0])
            ->willReturn($variantContent);

        $provider = $this->createProvider(
            new Request(query: ['variant' => 'RED']),
            VariantRouting::QueryParameter,
            $productRepository,
            $contentAggregator,
        );

        self::assertSame($variantContent, $provider->getCurrentVariant($productContent));
        // the resolver and the localizations resolver ask for it in the same request
        self::assertSame($variantContent, $provider->getCurrentVariant($productContent));
    }

    public function testAnUnknownCodeHasNoVariant(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('findBy')->willReturn([]);

        $provider = $this->createProvider(new Request(query: ['variant' => 'UNKNOWN']), VariantRouting::QueryParameter, $productRepository);

        self::assertNull($provider->getCurrentVariant($this->createProductContent()));
        self::assertNull($provider->getCurrentVariant($this->createProductContent()));
    }

    public function testAVariantWithoutPublishedContentHasNoVariant(): void
    {
        $productContent = $this->createProductContent();
        $variant = new Product('variant-uuid');
        $variant->setParent($productContent->getResource());

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturn([$variant]);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willThrowException(new ContentNotFoundException($variant, ['locale' => 'en']));

        $provider = $this->createProvider(new Request(query: ['variant' => 'RED']), VariantRouting::QueryParameter, $productRepository, $contentAggregator);

        self::assertNull($provider->getCurrentVariant($productContent));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidQueries(): iterable
    {
        yield 'no parameter' => [[]];
        yield 'empty code' => [['variant' => '']];
        yield 'array' => [['variant' => ['RED']]];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('provideInvalidQueries')]
    public function testAnInvalidQueryHasNoVariant(array $query): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findBy');

        $provider = $this->createProvider(new Request(query: $query), VariantRouting::QueryParameter, $productRepository);

        self::assertNull($provider->getCurrentVariant($this->createProductContent()));
    }

    public function testAProductWithoutLocaleHasNoVariant(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findBy');

        $provider = $this->createProvider(new Request(query: ['variant' => 'RED']), VariantRouting::QueryParameter, $productRepository);

        self::assertNull($provider->getCurrentVariant(new ProductDimensionContent(new Product('parent-uuid'))));
    }

    private function createProvider(
        Request $request,
        VariantRouting $routing = VariantRouting::Route,
        ?ProductRepositoryInterface $productRepository = null,
        ?ContentAggregatorInterface $contentAggregator = null,
    ): CurrentVariantProvider {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CurrentVariantProvider(
            $requestStack,
            $productRepository ?? $this->createStub(ProductRepositoryInterface::class),
            $contentAggregator ?? $this->createStub(ContentAggregatorInterface::class),
            $routing,
        );
    }

    private function createProductContent(): ProductDimensionContent
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $productContent = new ProductDimensionContent($product);
        $productContent->setLocale('en');

        return $productContent;
    }

    private function createVariantContent(ProductInterface $parent): ProductDimensionContent
    {
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        return new ProductDimensionContent($variant);
    }
}
