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
use Sulu\Product\Domain\Exception\InvalidMainCategoryException;

class InvalidMainCategoryExceptionTest extends TestCase
{
    public function testTranslationContract(): void
    {
        $exception = new InvalidMainCategoryException(5, 'shop');

        self::assertSame('sulu_product.invalid_main_category', $exception->getMessageTranslationKey());
        self::assertSame(['{id}' => 5, '{rootKey}' => 'shop'], $exception->getMessageTranslationParameters());
        self::assertStringContainsString('"shop"', $exception->getMessage());
        self::assertStringContainsString('5', $exception->getMessage());
    }
}
