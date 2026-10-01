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
use Sulu\Product\Domain\Exception\ProductVariantParentNotFoundException;

#[CoversClass(ProductVariantParentNotFoundException::class)]
class ProductVariantParentNotFoundExceptionTest extends TestCase
{
    public function testImplementsTranslationErrorMessageExceptionInterface(): void
    {
        $interfaces = \class_implements(ProductVariantParentNotFoundException::class);

        $this->assertIsArray($interfaces);
        $this->assertContains(TranslationErrorMessageExceptionInterface::class, $interfaces);
    }

    public function testGetMessage(): void
    {
        $exception = new ProductVariantParentNotFoundException('variant-uuid', 'parent-uuid');

        $this->assertSame(
            'The variant "variant-uuid" cannot be restored because its parent product "parent-uuid" does not exist.',
            $exception->getMessage()
        );
    }

    public function testGetUuids(): void
    {
        $exception = new ProductVariantParentNotFoundException('variant-uuid', 'parent-uuid');

        $this->assertSame('variant-uuid', $exception->getVariantUuid());
        $this->assertSame('parent-uuid', $exception->getParentUuid());
    }

    public function testGetMessageTranslationKey(): void
    {
        $exception = new ProductVariantParentNotFoundException('variant-uuid', 'parent-uuid');

        $this->assertSame('sulu_product.variant_parent_not_found', $exception->getMessageTranslationKey());
    }

    public function testGetMessageTranslationParameters(): void
    {
        $exception = new ProductVariantParentNotFoundException('variant-uuid', 'parent-uuid');

        $this->assertSame([], $exception->getMessageTranslationParameters());
    }
}
