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
use Sulu\Product\Domain\Exception\InvalidVariantAttributeException;

class InvalidVariantAttributeExceptionTest extends TestCase
{
    public function testTranslationContract(): void
    {
        $exception = new InvalidVariantAttributeException(7);

        self::assertSame('sulu_product.invalid_variant_attribute', $exception->getMessageTranslationKey());
        self::assertSame(['{attributeId}' => 7], $exception->getMessageTranslationParameters());
        self::assertStringContainsString('7', $exception->getMessage());
    }
}
