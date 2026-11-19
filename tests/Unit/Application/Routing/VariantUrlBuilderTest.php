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
use Sulu\Product\Application\Routing\VariantUrlBuilder;

#[CoversClass(VariantUrlBuilder::class)]
class VariantUrlBuilderTest extends TestCase
{
    public function testAppendsTheEncodedCodeAsTheConfiguredParameter(): void
    {
        self::assertSame('/t-shirt?variant=RED%2FXL', (new VariantUrlBuilder('variant'))->append('/t-shirt', 'RED/XL'));
        self::assertSame('/t-shirt?sku=RED', (new VariantUrlBuilder('sku'))->append('/t-shirt', 'RED'));
    }
}
