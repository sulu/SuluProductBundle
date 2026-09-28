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
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
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
        $provider = new CurrentVariantProvider(new RequestStack());

        self::assertNull($provider->getCurrentVariant(new ProductDimensionContent(new Product('parent-uuid'))));
    }

    private function createProvider(Request $request): CurrentVariantProvider
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new CurrentVariantProvider($requestStack);
    }

    private function createVariantContent(ProductInterface $parent): ProductDimensionContent
    {
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        return new ProductDimensionContent($variant);
    }
}
