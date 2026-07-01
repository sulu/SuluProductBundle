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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Product::class)]
class ProductTest extends TestCase
{
    public function testConstructorGeneratesUuidWhenNoneProvided(): void
    {
        $product = new Product();

        $this->assertTrue(Uuid::isValid($product->getUuid()));
        $this->assertSame($product->getUuid(), $product->getId());
    }

    public function testConstructorAcceptsProvidedUuid(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $product = new Product($uuid);

        $this->assertSame($uuid, $product->getUuid());
        $this->assertSame($uuid, $product->getId());
    }

    public function testCreateDimensionContentReturnsBoundInstance(): void
    {
        $product = new Product();
        $dimensionContent = $product->createDimensionContent();

        $this->assertInstanceOf(ProductDimensionContent::class, $dimensionContent);
        $this->assertSame($product, $dimensionContent->getResource());
    }
}
