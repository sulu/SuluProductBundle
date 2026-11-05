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
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductComponent::class)]
final class ProductComponentTest extends TestCase
{
    use ProphecyTrait;

    public function testConstructorAssignsReferencesAndDefaults(): void
    {
        $productDimensionContent = $this->prophesize(ProductDimensionContentInterface::class)->reveal();
        $componentProduct = $this->prophesize(ProductInterface::class)->reveal();

        $component = new ProductComponent($productDimensionContent, $componentProduct);

        $this->assertSame($productDimensionContent, $component->getProductDimensionContent());
        $this->assertSame($componentProduct, $component->getComponent());
        $this->assertSame(0, $component->getPosition());
        $this->assertNull($component->getId());
    }

    public function testConstructorAcceptsExplicitPosition(): void
    {
        $productDimensionContent = $this->prophesize(ProductDimensionContentInterface::class)->reveal();
        $componentProduct = $this->prophesize(ProductInterface::class)->reveal();

        $component = new ProductComponent($productDimensionContent, $componentProduct, 5);

        $this->assertSame(5, $component->getPosition());
    }

    public function testSetPositionRoundTrips(): void
    {
        $productDimensionContent = $this->prophesize(ProductDimensionContentInterface::class)->reveal();
        $componentProduct = $this->prophesize(ProductInterface::class)->reveal();

        $component = new ProductComponent($productDimensionContent, $componentProduct);
        $component->setPosition(5);

        $this->assertSame(5, $component->getPosition());
    }
}
