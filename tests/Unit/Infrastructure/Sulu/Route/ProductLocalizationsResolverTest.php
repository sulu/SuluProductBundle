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
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentLocalizationsResolver\ContentLocalizationsResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Route\CurrentVariantProvider;
use Sulu\Product\Infrastructure\Sulu\Route\ProductLocalizationsResolver;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ProductLocalizationsResolver::class)]
class ProductLocalizationsResolverTest extends TestCase
{
    private const LOCALIZATIONS = ['de' => ['url' => '/de/nc3fx-b', 'locale' => 'de', 'alternate' => true]];

    public function testAVariantUrlLinksTheVariantInEveryLocale(): void
    {
        $parentContent = new ProductDimensionContent(new Product('parent-uuid'));
        $variant = new Product('variant-uuid');
        $variant->setParent($parentContent->getResource());
        $variantContent = new ProductDimensionContent($variant);

        $routeLocalizationsResolver = $this->createMock(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->expects(self::once())->method('resolve')->with($variantContent, 'sulu-io')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $variantContent]));

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($parentContent, 'sulu-io'));
    }

    public function testOtherContentKeepsItsOwnLocalizations(): void
    {
        $productContent = new ProductDimensionContent(new Product('product-uuid'));

        $routeLocalizationsResolver = $this->createMock(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->expects(self::once())->method('resolve')->with($productContent, 'sulu-io')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, new Request());

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($productContent, 'sulu-io'));
    }

    public function testContentThatIsNoProductKeepsItsOwnLocalizations(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        $dimensionContent = $this->createStub(DimensionContentInterface::class);

        $routeLocalizationsResolver = $this->createMock(ContentLocalizationsResolverInterface::class);
        $routeLocalizationsResolver->expects(self::once())->method('resolve')->with($dimensionContent, 'sulu-io')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($routeLocalizationsResolver, new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => new ProductDimensionContent($variant)]));

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($dimensionContent, 'sulu-io'));
    }

    private function createResolver(ContentLocalizationsResolverInterface $routeLocalizationsResolver, Request $request): ProductLocalizationsResolver
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ProductLocalizationsResolver($routeLocalizationsResolver, new CurrentVariantProvider($requestStack));
    }
}
