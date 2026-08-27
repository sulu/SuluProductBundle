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

namespace Sulu\Product\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Mapper\ProductParentMapper;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(ProductParentMapper::class)]
class ProductParentMapperTest extends TestCase
{
    use ProphecyTrait;

    public function testSetsTypeAndParentForVariant(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->getOneBy(['uuid' => $parent->getUuid()])->willReturn($parent);

        $mapper = new ProductParentMapper($repository->reveal());

        $variant = new Product();
        $mapper->mapProductData($variant, [
            'type' => ProductInterface::TYPE_VARIANT,
            'parent' => $parent->getUuid(),
        ]);

        self::assertSame(ProductInterface::TYPE_VARIANT, $variant->getType());
        self::assertSame($parent, $variant->getParent());
    }

    public function testDropsParentForNonVariantType(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $product = new Product();
        $product->setParent(new Product('11111111-1111-7111-8111-111111111111'));

        $mapper->mapProductData($product, [
            'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            'parent' => '11111111-1111-7111-8111-111111111111',
        ]);

        self::assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $product->getType());
        self::assertNull($product->getParent());
    }

    public function testRejectsVariantWithoutParent(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $this->expectException(InvalidArgumentException::class);

        $mapper->mapProductData(new Product(), ['type' => ProductInterface::TYPE_VARIANT]);
    }

    public function testRejectsNonStringType(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $this->expectException(InvalidArgumentException::class);

        $mapper->mapProductData(new Product(), ['type' => 42]);
    }

    public function testNoopWhenTypeIsMissing(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $product = new Product();
        $product->setParent($parent);

        $mapper->mapProductData($product, ['title' => 'x', 'parent' => 'other-uuid']);

        self::assertSame(ProductInterface::TYPE_PRODUCT, $product->getType());
        self::assertSame($parent, $product->getParent());
    }

    public function testTheVariantPositionIsMapped(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->getOneBy(['uuid' => $parent->getUuid()])->willReturn($parent);

        $mapper = new ProductParentMapper($repository->reveal());

        $variant = new Product();
        $mapper->mapProductData($variant, [
            'type' => ProductInterface::TYPE_VARIANT,
            'parent' => $parent->getUuid(),
            'position' => 3,
        ]);

        self::assertSame(3, $variant->getPosition());
    }

    public function testAProductThatIsNoLongerAVariantLosesItsPosition(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $product = new Product();
        $product->setPosition(4);

        $mapper->mapProductData($product, ['type' => ProductInterface::TYPE_PRODUCT]);

        self::assertSame(0, $product->getPosition());
    }
}
