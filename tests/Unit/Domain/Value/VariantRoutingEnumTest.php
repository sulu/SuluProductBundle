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

namespace Sulu\Product\Tests\Unit\Domain\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Value\VariantRoutingEnum;

#[CoversClass(VariantRoutingEnum::class)]
class VariantRoutingEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{VariantRoutingEnum, string, bool}>
     */
    public static function provideOwnsRoute(): iterable
    {
        yield 'per variant, product' => [VariantRoutingEnum::PER_VARIANT, ProductInterface::TYPE_PRODUCT, true];
        yield 'per variant, product with variants' => [VariantRoutingEnum::PER_VARIANT, ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, false];
        yield 'per variant, variant' => [VariantRoutingEnum::PER_VARIANT, ProductInterface::TYPE_VARIANT, true];
        yield 'per product, product' => [VariantRoutingEnum::PER_PRODUCT, ProductInterface::TYPE_PRODUCT, true];
        yield 'per product, product with variants' => [VariantRoutingEnum::PER_PRODUCT, ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, true];
        yield 'per product, variant' => [VariantRoutingEnum::PER_PRODUCT, ProductInterface::TYPE_VARIANT, false];
    }

    #[DataProvider('provideOwnsRoute')]
    public function testOwnsRoute(VariantRoutingEnum $routing, string $type, bool $expected): void
    {
        self::assertSame($expected, $routing->ownsRoute($type));
    }
}
