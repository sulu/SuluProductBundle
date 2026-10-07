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
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Application\Routing\VariantSlugResolver;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Model\Route;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;

#[CoversClass(VariantSlugResolver::class)]
#[CoversClass(VariantRouting::class)]
class VariantSlugResolverTest extends TestCase
{
    public function testRouteModeUsesTheOwnRoute(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::never())->method('findFirstBy');

        $resolver = new VariantSlugResolver($routeRepository, VariantRouting::Route);

        self::assertFalse($resolver->isQueryParameter());
        self::assertSame('/t-shirt-red', $resolver->resolve($this->createVariantContent('/t-shirt-red')));
        self::assertNull($resolver->resolve($this->createVariantContent(null)));
    }

    public function testQueryParameterModeLinksAVariantByItsProductSlug(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::once())->method('findFirstBy')
            ->with(['resourceKey' => ProductInterface::RESOURCE_KEY, 'resourceId' => 'parent-uuid', 'locale' => 'en'])
            ->willReturn(new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'));

        $resolver = new VariantSlugResolver($routeRepository, VariantRouting::QueryParameter);

        self::assertTrue($resolver->isQueryParameter());
        self::assertSame('/t-shirt?variant=RED%2FXL', $resolver->resolve($this->createVariantContent('/stale', 'RED/XL')));
        // memoized: the second variant of the same product asks the repository no more
        self::assertSame('/t-shirt?variant=BLUE', $resolver->resolve($this->createVariantContent(null, 'BLUE')));
        self::assertSame('/t-shirt?variant=GREEN', $resolver->resolve($this->createVariantContent(null, 'IGNORED'), 'GREEN'));
    }

    public function testResetForgetsTheProductSlugs(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::exactly(2))->method('findFirstBy')
            ->willReturn(new Route(ProductInterface::RESOURCE_KEY, 'parent-uuid', 'en', '/t-shirt'));

        $resolver = new VariantSlugResolver($routeRepository, VariantRouting::QueryParameter);

        $resolver->resolveVariant('parent-uuid', 'RED', 'en');
        $resolver->reset();
        $resolver->resolveVariant('parent-uuid', 'RED', 'en');
    }

    public function testQueryParameterModeHasNoUrlForAVariantWhoseProductHasNoRoute(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::once())->method('findFirstBy')->willReturn(null);

        $resolver = new VariantSlugResolver($routeRepository, VariantRouting::QueryParameter);

        self::assertNull($resolver->resolve($this->createVariantContent(null, 'RED')));
        self::assertNull($resolver->resolveVariant('parent-uuid', 'BLUE', 'en'));
    }

    public function testQueryParameterModeHasNoUrlForAVariantWithoutCode(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::never())->method('findFirstBy');

        $resolver = new VariantSlugResolver($routeRepository, VariantRouting::QueryParameter);

        self::assertNull($resolver->resolve($this->createVariantContent('/stale')));
    }

    public function testQueryParameterModeKeepsTheOwnRouteOfAProductWithoutParentOrLocale(): void
    {
        $routeRepository = $this->createMock(RouteRepositoryInterface::class);
        $routeRepository->expects(self::never())->method('findFirstBy');

        $resolver = new VariantSlugResolver($routeRepository, VariantRouting::QueryParameter);

        $productContent = new ProductDimensionContent(new Product('product-uuid'));
        $productContent->setLocale('en');
        $productContent->setRoute(new Route(ProductInterface::RESOURCE_KEY, 'product-uuid', 'en', '/t-shirt'));
        self::assertSame('/t-shirt', $resolver->resolve($productContent));

        $variantWithoutLocale = $this->createVariantContent('/stale', 'RED');
        $variantWithoutLocale->setLocale(null);
        self::assertSame('/stale', $resolver->resolve($variantWithoutLocale));
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
