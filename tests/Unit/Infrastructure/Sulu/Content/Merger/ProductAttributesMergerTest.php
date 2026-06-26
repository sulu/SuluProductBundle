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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Merger;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\ProductAttributesMerger;

#[CoversClass(ProductAttributesMerger::class)]
class ProductAttributesMergerTest extends TestCase
{
    use ProphecyTrait;

    private ProductAttributesMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new ProductAttributesMerger();
    }

    private function makeDimensionContent(): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product(new ProductFamily()));
    }

    public function testEarlyReturnWhenTargetNotProductDimensionContent(): void
    {
        $source = $this->makeDimensionContent();
        $target = new \stdClass();

        $this->merger->merge($target, $source);

        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenSourceNotProductDimensionContent(): void
    {
        $target = $this->makeDimensionContent();
        $source = new \stdClass();

        $this->merger->merge($target, $source);

        $this->addToAssertionCount(1);
    }

    public function testMergesAttributesFromSource(): void
    {
        /** @var ObjectProphecy<ProductAttributeValueInterface> $attrValue */
        $attrValue = $this->prophesize(ProductAttributeValueInterface::class);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getAttributes()->willReturn(new ArrayCollection([$attrValue->reveal()]));

        /** @var ObjectProphecy<ProductDimensionContentInterface> $target */
        $target = $this->prophesize(ProductDimensionContentInterface::class);
        $target->addAttribute($attrValue->reveal())->shouldBeCalled()->willReturn($target->reveal());

        $this->merger->merge($target->reveal(), $source->reveal());
    }

    public function testEmptySourceResultsInNoAttributesAdded(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getAttributes()->willReturn(new ArrayCollection());

        /** @var ObjectProphecy<ProductDimensionContentInterface> $target */
        $target = $this->prophesize(ProductDimensionContentInterface::class);
        $target->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->merger->merge($target->reveal(), $source->reveal());
    }
}
