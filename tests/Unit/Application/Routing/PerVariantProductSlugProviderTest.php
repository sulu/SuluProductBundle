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
use Sulu\Product\Application\Routing\PerVariantProductSlugProvider;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(PerVariantProductSlugProvider::class)]
class PerVariantProductSlugProviderTest extends TestCase
{
    public function testUsesTheOwnRoute(): void
    {
        $provider = new PerVariantProductSlugProvider();

        $content = new ProductDimensionContent(new Product('variant-uuid'));
        self::assertNull($provider->getSlug($content));

        $content->setRoute(new Route(ProductInterface::RESOURCE_KEY, 'variant-uuid', 'en', '/t-shirt-red'));
        self::assertSame('/t-shirt-red', $provider->getSlug($content, 'IGNORED'));
    }

    public function testEveryTypeButTheProductWithVariantsOwnsARoute(): void
    {
        $provider = new PerVariantProductSlugProvider();

        self::assertTrue($provider->ownsRoute(ProductInterface::TYPE_PRODUCT));
        self::assertTrue($provider->ownsRoute(ProductInterface::TYPE_VARIANT));
        self::assertFalse($provider->ownsRoute(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS));
    }
}
