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
use Sulu\Component\Rest\Exception\TranslationErrorMessageExceptionInterface;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;

#[CoversClass(ProductCodeNotUniqueException::class)]
class ProductCodeNotUniqueExceptionTest extends TestCase
{
    public function testImplementsTranslationErrorMessageExceptionInterface(): void
    {
        $interfaces = \class_implements(ProductCodeNotUniqueException::class);

        $this->assertIsArray($interfaces);
        $this->assertContains(TranslationErrorMessageExceptionInterface::class, $interfaces);
    }

    public function testGetMessage(): void
    {
        $exception = new ProductCodeNotUniqueException('SKU-001');

        $this->assertSame(
            'A product with the code "SKU-001" is already in use.',
            $exception->getMessage()
        );
    }

    public function testGetMessageTranslationKey(): void
    {
        $exception = new ProductCodeNotUniqueException('SKU-001');

        $this->assertSame('sulu_product.code_already_used', $exception->getMessageTranslationKey());
    }

    public function testGetMessageTranslationParameters(): void
    {
        $exception = new ProductCodeNotUniqueException('SKU-001');

        $this->assertSame(['{code}' => 'SKU-001'], $exception->getMessageTranslationParameters());
    }

    public function testGetMessageTranslationParametersWithDifferentCode(): void
    {
        $exception = new ProductCodeNotUniqueException('foo-bar-42');

        $this->assertSame(['{code}' => 'foo-bar-42'], $exception->getMessageTranslationParameters());
    }
}
