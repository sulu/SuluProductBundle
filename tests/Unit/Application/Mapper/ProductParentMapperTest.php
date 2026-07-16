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

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Mapper\ProductParentMapper;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductParentMapperTest extends TestCase
{
    use ProphecyTrait;

    public function testSetsTypeFromData(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $product = new Product();
        $mapper->mapProductData($product, ['type' => ProductInterface::TYPE_VARIANT]);

        self::assertSame(ProductInterface::TYPE_VARIANT, $product->getType());
    }

    public function testSetsParentFromUuid(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->getOneBy(['uuid' => $parent->getUuid()])->willReturn($parent);

        $mapper = new ProductParentMapper($repository->reveal());

        $variant = new Product();
        $mapper->mapProductData($variant, ['parent' => $parent->getUuid()]);

        self::assertSame($parent, $variant->getParent());
    }

    public function testNoopWhenNeitherKeyPresent(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $mapper = new ProductParentMapper($repository->reveal());

        $product = new Product();
        $mapper->mapProductData($product, ['title' => 'x']);

        self::assertSame(ProductInterface::TYPE_SIMPLE, $product->getType());
        self::assertNull($product->getParent());
    }
}
