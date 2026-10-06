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
use Sulu\Product\Domain\Exception\AttributeKeyNotUniqueException;

#[CoversClass(AttributeKeyNotUniqueException::class)]
class AttributeKeyNotUniqueExceptionTest extends TestCase
{
    public function testCarriesKeyTranslationKeyAndParameters(): void
    {
        $exception = new AttributeKeyNotUniqueException('color');

        $this->assertSame('The attribute key "color" is already in use.', $exception->getMessage());
        $this->assertSame('color', $exception->getAttributeKey());
        $this->assertSame('sulu_product.attribute_key_already_used', $exception->getMessageTranslationKey());
        $this->assertSame(['{key}' => 'color'], $exception->getMessageTranslationParameters());
    }
}
