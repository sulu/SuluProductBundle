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

use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Exception\InvalidProductStatusException;

class InvalidProductStatusExceptionTest extends TestCase
{
    public function testTranslationContract(): void
    {
        $exception = new InvalidProductStatusException('sold_out', ['announced', 'available']);

        self::assertSame('sulu_product.invalid_status', $exception->getMessageTranslationKey());
        self::assertSame(['{status}' => 'sold_out'], $exception->getMessageTranslationParameters());
        self::assertStringContainsString('sold_out', $exception->getMessage());
    }
}
