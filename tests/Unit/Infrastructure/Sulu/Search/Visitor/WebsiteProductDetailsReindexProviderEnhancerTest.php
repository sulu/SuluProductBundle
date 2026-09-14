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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search\Visitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductDetailsReindexProviderEnhancer;

#[CoversClass(WebsiteProductDetailsReindexProviderEnhancer::class)]
class WebsiteProductDetailsReindexProviderEnhancerTest extends TestCase
{
    public function testTextValueJoinsKeyAndValue(): void
    {
        $this->assertSame('colour:black', WebsiteProductDetailsReindexProviderEnhancer::textValue('colour', 'black'));
        $this->assertSame('cable_length:5 m', WebsiteProductDetailsReindexProviderEnhancer::textValue('cable-length', '5 m'));
    }

    public function testNumericFieldSanitisesKey(): void
    {
        $this->assertSame('cable_length', WebsiteProductDetailsReindexProviderEnhancer::numericField('cable_length'));
        $this->assertSame('cable_length_mm', WebsiteProductDetailsReindexProviderEnhancer::numericField('cable-length.mm'));
    }
}
