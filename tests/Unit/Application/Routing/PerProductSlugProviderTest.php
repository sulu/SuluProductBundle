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

namespace Sulu\Product\Tests\Unit\Application\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Routing\PerProductSlugProvider;
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Model\Route;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;

#[CoversClass(PerProductSlugProvider::class)]
class PerProductSlugProviderTest extends TestCase
{
    public function testLinksAVariantByItsProductSlug(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::once())->method('findFirstBy')
            ->with(['resourceKey' => ProductInterface::RESOURCE_KEY, 'resourceId' => 'parent-uuid', 'locale' => 'en'])
            ->willReturn(new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'));

        $provider = $this->createProvider($routeRepository);

        self::assertSame('/t-shirt?variant=RED%2FXL', $provider->getSlug($this->createVariantContent('/stale', 'RED/XL')));
        // memoized: the second variant of the same product asks the repository no more
        self::assertSame('/t-shirt?variant=BLUE', $provider->getSlug($this->createVariantContent(null, 'BLUE')));
        self::assertSame('/t-shirt?variant=GREEN', $provider->getSlug($this->createVariantContent(null, 'IGNORED'), 'GREEN'));
    }

    public function testMemoizesTheProductSlugPerLocale(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::exactly(2))->method('findFirstBy')
            ->willReturnOnConsecutiveCalls(
                new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'),
                new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'de', '/t-shirt-de'),
            );

        $provider = $this->createProvider($routeRepository);

        $german = $this->createVariantContent(null, 'RED');
        $german->setLocale('de');

        self::assertSame('/t-shirt?variant=RED', $provider->getSlug($this->createVariantContent(null, 'RED')));
        self::assertSame('/t-shirt-de?variant=RED', $provider->getSlug($german));
        self::assertSame('/t-shirt-de?variant=RED', $provider->getSlug($german));
    }

    public function testUsesTheConfiguredParameter(): void
    {
        $routeRepository = $this->createStub(RouteRepositoryInterface::class);
        $routeRepository->method('findFirstBy')->willReturn(new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'));

        $provider = $this->createProvider($routeRepository, 'sku');

        self::assertSame('/t-shirt?sku=RED', $provider->getSlug($this->createVariantContent(null, 'RED')));
    }

    public function testResetForgetsTheProductSlugs(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::exactly(2))->method('findFirstBy')
            ->willReturn(new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'));

        $provider = $this->createProvider($routeRepository);

        $provider->getSlug($this->createVariantContent(null, 'RED'));
        $provider->reset();
        $provider->getSlug($this->createVariantContent(null, 'RED'));
    }

    public function testHasNoUrlForAVariantWhoseProductHasNoRoute(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::once())->method('findFirstBy')->willReturn(null);

        self::assertNull($this->createProvider($routeRepository)->getSlug($this->createVariantContent(null, 'RED')));
    }

    public function testHasNoUrlForAVariantWithoutCode(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::never())->method('findFirstBy');

        self::assertNull($this->createProvider($routeRepository)->getSlug($this->createVariantContent('/stale')));
    }

    public function testKeepsTheOwnRouteOfAProductWithoutParentOrLocale(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::never())->method('findFirstBy');

        $provider = $this->createProvider($routeRepository);

        $productContent = new ProductDimensionContent(new Product('product-uuid'));
        $productContent->setLocale('en');
        $productContent->setRoute(new Route(ProductInterface::RESOURCE_KEY, 'product-uuid', 'en', '/t-shirt'));
        self::assertSame('/t-shirt', $provider->getSlug($productContent));

        $variantWithoutLocale = $this->createVariantContent('/stale', 'RED');
        $variantWithoutLocale->setLocale(null);
        self::assertSame('/stale', $provider->getSlug($variantWithoutLocale));
    }

    public function testEveryTypeButTheVariantOwnsARoute(): void
    {
        $provider = $this->createProvider($this->createStub(RouteRepositoryInterface::class));

        self::assertTrue($provider->ownsRoute(ProductInterface::TYPE_PRODUCT));
        self::assertTrue($provider->ownsRoute(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS));
        self::assertFalse($provider->ownsRoute(ProductInterface::TYPE_VARIANT));
    }

    private function createProvider(RouteRepositoryInterface $routeRepository, string $parameter = 'variant'): PerProductSlugProvider
    {
        return new PerProductSlugProvider($routeRepository, new VariantUrlBuilder($parameter));
    }

    private function createVariantContent(?string $slug, ?string $code = null): ProductDimensionContent
    {
        $variant = new Product('variant-uuid');
        $variant->setParent(new Product('parent-uuid'));

        $content = new ProductDimensionContent($variant);
        $content->setLocale('en');
        $content->setCode($code);

        if (null !== $slug) {
            $content->setRoute(new Route(ProductInterface::RESOURCE_KEY, 'variant-uuid', 'en', $slug));
        }

        return $content;
    }
}
