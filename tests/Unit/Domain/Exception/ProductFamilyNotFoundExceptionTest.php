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

namespace Sulu\Product\Tests\Unit\Domain\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;

#[CoversClass(ProductFamilyNotFoundException::class)]
class ProductFamilyNotFoundExceptionTest extends TestCase
{
    public function testMessageAndCriteria(): void
    {
        $previous = new \RuntimeException('boom');
        $exception = new ProductFamilyNotFoundException(['uuid' => 'abc'], $previous);

        $this->assertStringContainsString('abc', $exception->getMessage());
        $this->assertSame(['uuid' => 'abc'], $exception->getCriteria());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
