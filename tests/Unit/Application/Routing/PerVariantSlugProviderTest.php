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
use Sulu\Product\Application\Routing\PerVariantSlugProvider;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(PerVariantSlugProvider::class)]
class PerVariantSlugProviderTest extends TestCase
{
    public function testEveryTypeButTheProductWithVariantsOwnsARoute(): void
    {
        $provider = new PerVariantSlugProvider();

        self::assertTrue($provider->ownsRoute(ProductInterface::TYPE_PRODUCT));
        self::assertTrue($provider->ownsRoute(ProductInterface::TYPE_VARIANT));
        self::assertFalse($provider->ownsRoute(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS));
    }
}
