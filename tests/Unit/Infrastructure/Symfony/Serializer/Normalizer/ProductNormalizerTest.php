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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Symfony\Serializer\Normalizer\ProductNormalizer;

#[CoversClass(ProductNormalizer::class)]
class ProductNormalizerTest extends TestCase
{
    private function normalizer(): ProductNormalizer
    {
        return new ProductNormalizer();
    }

    public function testSupportsNormalizationReturnsTrueForProduct(): void
    {
        $product = new Product();

        $this->assertTrue($this->normalizer()->supportsNormalization($product));
        $this->assertFalse($this->normalizer()->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        $supported = $this->normalizer()->getSupportedTypes(null);

        $this->assertArrayHasKey(ProductInterface::class, $supported);
        $this->assertTrue($supported[ProductInterface::class]);
    }

    public function testNormalizeReturnsId(): void
    {
        $product = new Product('product-uuid');

        $result = $this->normalizer()->normalize($product);

        $this->assertSame('product-uuid', $result['id']);
    }
}
