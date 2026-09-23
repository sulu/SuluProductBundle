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
use Sulu\Product\Domain\Exception\InvalidDateDisplayFormatException;

#[CoversClass(InvalidDateDisplayFormatException::class)]
class InvalidDateDisplayFormatExceptionTest extends TestCase
{
    public function testGetMessage(): void
    {
        $exception = new InvalidDateDisplayFormatException('Released MMMM yyyy');

        $this->assertSame(
            'The date display format "Released MMMM yyyy" renders no date. Put literal words in single quotes, e.g. \'Released\' MMMM yyyy.',
            $exception->getMessage(),
        );
    }

    public function testGetDisplayFormat(): void
    {
        $exception = new InvalidDateDisplayFormatException('Released MMMM yyyy');

        $this->assertSame('Released MMMM yyyy', $exception->getDisplayFormat());
    }
}
