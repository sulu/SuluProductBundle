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
use Sulu\Product\Domain\Exception\ProductFamilyKeyNotUniqueException;

#[CoversClass(ProductFamilyKeyNotUniqueException::class)]
class ProductFamilyKeyNotUniqueExceptionTest extends TestCase
{
    public function testCarriesTranslationKeyAndParameters(): void
    {
        $exception = new ProductFamilyKeyNotUniqueException('shoes');

        $this->assertSame('A product family with the key "shoes" already exists.', $exception->getMessage());
        $this->assertSame('sulu_product.product_family_key_already_used', $exception->getMessageTranslationKey());
        $this->assertSame(['{key}' => 'shoes'], $exception->getMessageTranslationParameters());
    }
}
