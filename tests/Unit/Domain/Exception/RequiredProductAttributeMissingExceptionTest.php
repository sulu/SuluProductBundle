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
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;

#[CoversClass(RequiredProductAttributeMissingException::class)]
class RequiredProductAttributeMissingExceptionTest extends TestCase
{
    public function testCarriesAttributeKey(): void
    {
        $exception = new RequiredProductAttributeMissingException('weight');

        self::assertSame('weight', $exception->getAttributeKey());
        self::assertStringContainsString('weight', $exception->getMessage());
    }

    public function testWrapsPreviousThrowable(): void
    {
        $previous = new \RuntimeException('boom');

        $exception = new RequiredProductAttributeMissingException('weight', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
