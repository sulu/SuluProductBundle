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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\ProductAssociationsMerger;

#[CoversClass(ProductAssociationsMerger::class)]
final class ProductAssociationsMergerTest extends TestCase
{
    use ProphecyTrait;

    private ProductAssociationsMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new ProductAssociationsMerger();
    }

    public function testEarlyReturnWhenTargetNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getAssociations(Argument::cetera())->shouldNotBeCalled();

        $target = new \stdClass();

        $this->merger->merge($target, $source->reveal());

        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenSourceNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $target */
        $target = $this->prophesize(ProductDimensionContentInterface::class);
        $target->addAssociation(Argument::cetera())->shouldNotBeCalled();

        $source = new \stdClass();

        $this->merger->merge($target->reveal(), $source);

        $this->addToAssertionCount(1);
    }

    public function testMergesAssociationsFromSource(): void
    {
        /** @var ObjectProphecy<ProductAssociationInterface> $association1 */
        $association1 = $this->prophesize(ProductAssociationInterface::class);
        /** @var ObjectProphecy<ProductAssociationInterface> $association2 */
        $association2 = $this->prophesize(ProductAssociationInterface::class);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getAssociations()->willReturn([$association1->reveal(), $association2->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $target */
        $target = $this->prophesize(ProductDimensionContentInterface::class);
        $target->addAssociation($association1->reveal())->shouldBeCalled();
        $target->addAssociation($association2->reveal())->shouldBeCalled();

        $this->merger->merge($target->reveal(), $source->reveal());
    }
}
