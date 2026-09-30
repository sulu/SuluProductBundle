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

namespace Sulu\Product\Tests\Unit\Application\Ai;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Ai\AttributeFilter;

#[CoversClass(AttributeFilter::class)]
class AttributeFilterTest extends TestCase
{
    public function testExposesKeyAndValue(): void
    {
        $filter = new AttributeFilter('color', 'red');

        $this->assertSame('color', $filter->key);
        $this->assertSame('red', $filter->value);
    }
}
