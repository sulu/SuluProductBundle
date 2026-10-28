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
use Sulu\Product\Domain\Exception\InvalidAttributeKeyException;

#[CoversClass(InvalidAttributeKeyException::class)]
class InvalidAttributeKeyExceptionTest extends TestCase
{
    public function testGetMessageAndKey(): void
    {
        $exception = new InvalidAttributeKeyException('10');

        $this->assertSame('The attribute key "10" is a number. Use a key with at least one letter, e.g. "size_10".', $exception->getMessage());
        $this->assertSame('10', $exception->getKey());
    }
}
