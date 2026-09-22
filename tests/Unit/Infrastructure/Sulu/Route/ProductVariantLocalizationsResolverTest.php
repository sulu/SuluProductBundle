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
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Sulu\Product\Infrastructure\Sulu\Route\ProductVariantLocalizationsResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ProductVariantLocalizationsResolver::class)]
class ProductVariantLocalizationsResolverTest extends TestCase
{
    private const LOCALIZATIONS = ['de' => ['url' => '/de/nc3fx-b', 'locale' => 'de', 'alternate' => true]];

    public function testAVariantUrlLinksTheVariantInEveryLocale(): void
    {
        $parentContent = new ProductDimensionContent(new Product('parent-uuid'));
        $variant = new Product('variant-uuid');
        $variant->setParent($parentContent->getResource());
        $variantContent = new ProductDimensionContent($variant);

        $inner = $this->createMock(ContentLocalizationsResolverInterface::class);
        $inner->expects(self::once())->method('resolve')->with($variantContent, 'sulu-io')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($inner, new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $variantContent]));

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($parentContent, 'sulu-io'));
    }

    public function testOtherContentKeepsItsOwnLocalizations(): void
    {
        $productContent = new ProductDimensionContent(new Product('product-uuid'));

        $inner = $this->createMock(ContentLocalizationsResolverInterface::class);
        $inner->expects(self::once())->method('resolve')->with($productContent, 'sulu-io')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($inner, new Request());

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($productContent, 'sulu-io'));
    }

    public function testContentThatIsNoProductKeepsItsOwnLocalizations(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        $dimensionContent = $this->createStub(DimensionContentInterface::class);

        $inner = $this->createMock(ContentLocalizationsResolverInterface::class);
        $inner->expects(self::once())->method('resolve')->with($dimensionContent, 'sulu-io')->willReturn(self::LOCALIZATIONS);

        $resolver = $this->createResolver($inner, new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => new ProductDimensionContent($variant)]));

        self::assertSame(self::LOCALIZATIONS, $resolver->resolve($dimensionContent, 'sulu-io'));
    }

    private function createResolver(ContentLocalizationsResolverInterface $inner, Request $request): ProductVariantLocalizationsResolver
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ProductVariantLocalizationsResolver($inner, new CurrentVariantProvider($requestStack));
    }
}
