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
use Sulu\Product\Domain\Exception\InvalidVariantParentException;

#[CoversClass(InvalidVariantParentException::class)]
class InvalidVariantParentExceptionTest extends TestCase
{
    public function testCarriesMessageHintAndPreviousException(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new InvalidVariantParentException('Variant not found: x', 'Verify the UUID.', $previous);

        $this->assertSame('Variant not found: x', $exception->getMessage());
        $this->assertSame('Verify the UUID.', $exception->getHint());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
