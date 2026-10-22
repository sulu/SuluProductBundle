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
use Sulu\Product\Domain\Model\ProductComponentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\ProductComponentsMerger;

#[CoversClass(ProductComponentsMerger::class)]
final class ProductComponentsMergerTest extends TestCase
{
    use ProphecyTrait;

    private ProductComponentsMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new ProductComponentsMerger();
    }

    public function testEarlyReturnWhenTargetNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getComponents(Argument::cetera())->shouldNotBeCalled();

        $this->merger->merge(new \stdClass(), $source->reveal());

        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenSourceNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $target */
        $target = $this->prophesize(ProductDimensionContentInterface::class);
        $target->addComponent(Argument::cetera())->shouldNotBeCalled();

        $this->merger->merge($target->reveal(), new \stdClass());

        $this->addToAssertionCount(1);
    }

    public function testMergesComponentsFromSource(): void
    {
        /** @var ObjectProphecy<ProductComponentInterface> $component1 */
        $component1 = $this->prophesize(ProductComponentInterface::class);
        /** @var ObjectProphecy<ProductComponentInterface> $component2 */
        $component2 = $this->prophesize(ProductComponentInterface::class);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getComponents()->willReturn([$component1->reveal(), $component2->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $target */
        $target = $this->prophesize(ProductDimensionContentInterface::class);
        $target->addComponent($component1->reveal())->shouldBeCalled();
        $target->addComponent($component2->reveal())->shouldBeCalled();

        $this->merger->merge($target->reveal(), $source->reveal());
    }
}
