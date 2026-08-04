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
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductAssociation::class)]
final class ProductAssociationTest extends TestCase
{
    use ProphecyTrait;

    public function testConstructorAssignsReferencesAndDefaults(): void
    {
        $productDimensionContent = $this->prophesize(ProductDimensionContentInterface::class)->reveal();
        $target = $this->prophesize(ProductInterface::class)->reveal();

        $association = new ProductAssociation($productDimensionContent, $target, 'accessory');

        $this->assertSame($productDimensionContent, $association->getProductDimensionContent());
        $this->assertSame($target, $association->getTarget());
        $this->assertSame('accessory', $association->getType());
        $this->assertSame(0, $association->getPosition());
        $this->assertNull($association->getId());
    }

    public function testConstructorAcceptsExplicitPosition(): void
    {
        $productDimensionContent = $this->prophesize(ProductDimensionContentInterface::class)->reveal();
        $target = $this->prophesize(ProductInterface::class)->reveal();

        $association = new ProductAssociation($productDimensionContent, $target, 'accessory', 5);

        $this->assertSame(5, $association->getPosition());
    }

    public function testSetPositionRoundTrips(): void
    {
        $productDimensionContent = $this->prophesize(ProductDimensionContentInterface::class)->reveal();
        $target = $this->prophesize(ProductInterface::class)->reveal();

        $association = new ProductAssociation($productDimensionContent, $target, 'accessory');
        $association->setPosition(5);

        $this->assertSame(5, $association->getPosition());
    }
}
