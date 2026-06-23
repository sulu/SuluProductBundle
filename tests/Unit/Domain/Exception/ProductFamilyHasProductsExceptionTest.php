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
use Sulu\Product\Domain\Exception\ProductFamilyHasProductsException;

#[CoversClass(ProductFamilyHasProductsException::class)]
class ProductFamilyHasProductsExceptionTest extends TestCase
{
    public function testImplementsTranslationErrorMessageExceptionInterface(): void
    {
        $interfaces = \class_implements(ProductFamilyHasProductsException::class);

        $this->assertIsArray($interfaces);
        $this->assertContains(TranslationErrorMessageExceptionInterface::class, $interfaces);
    }

    public function testGetMessage(): void
    {
        $exception = new ProductFamilyHasProductsException('family-uuid');

        $this->assertSame(
            'The product family "family-uuid" cannot be removed because products are still assigned to it.',
            $exception->getMessage()
        );
    }

    public function testGetProductFamilyUuid(): void
    {
        $exception = new ProductFamilyHasProductsException('family-uuid');

        $this->assertSame('family-uuid', $exception->getProductFamilyUuid());
    }

    public function testGetMessageTranslationKey(): void
    {
        $exception = new ProductFamilyHasProductsException('family-uuid');

        $this->assertSame('sulu_product.product_family_has_products', $exception->getMessageTranslationKey());
    }

    public function testGetMessageTranslationParameters(): void
    {
        $exception = new ProductFamilyHasProductsException('family-uuid');

        $this->assertSame([], $exception->getMessageTranslationParameters());
    }
}
