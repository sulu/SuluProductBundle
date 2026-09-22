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
use Sulu\Product\Domain\Exception\AttributeOptionKeyNotUniqueException;

#[CoversClass(AttributeOptionKeyNotUniqueException::class)]
class AttributeOptionKeyNotUniqueExceptionTest extends TestCase
{
    public function testGetMessage(): void
    {
        $exception = new AttributeOptionKeyNotUniqueException('red');

        $this->assertSame('The option key "red" is used more than once.', $exception->getMessage());
    }

    public function testGetOptionKey(): void
    {
        $exception = new AttributeOptionKeyNotUniqueException('red');

        $this->assertSame('red', $exception->getOptionKey());
    }
}
