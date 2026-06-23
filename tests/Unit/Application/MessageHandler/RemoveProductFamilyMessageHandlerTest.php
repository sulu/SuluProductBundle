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

namespace Sulu\Product\Tests\Unit\Application\MessageHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Message\RemoveProductFamilyMessage;
use Sulu\Product\Application\MessageHandler\RemoveProductFamilyMessageHandler;
use Sulu\Product\Domain\Exception\ProductFamilyHasProductsException;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class RemoveProductFamilyMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $familyRepository;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    protected function setUp(): void
    {
        $this->familyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
    }

    private function createHandler(): RemoveProductFamilyMessageHandler
    {
        return new RemoveProductFamilyMessageHandler(
            $this->familyRepository->reveal(),
            $this->productRepository->reveal(),
        );
    }

    public function testRemovesFoundFamily(): void
    {
        $family = new ProductFamily();
        $this->familyRepository->findOneBy(['uuid' => 'f'])->willReturn($family);
        $this->productRepository->existBy(['productFamilyUuid' => 'f'])->willReturn(false);
        $this->familyRepository->remove($family)->shouldBeCalledOnce();

        ($this->createHandler())(new RemoveProductFamilyMessage('f'));
    }

    public function testThrowsWhenMissing(): void
    {
        $this->familyRepository->findOneBy(['uuid' => 'missing'])->willReturn(null);
        $this->familyRepository->remove(Argument::any())->shouldNotBeCalled();

        $this->expectException(ProductFamilyNotFoundException::class);
        ($this->createHandler())(new RemoveProductFamilyMessage('missing'));
    }

    public function testThrowsWhenProductsStillAssigned(): void
    {
        $family = new ProductFamily();
        $this->familyRepository->findOneBy(['uuid' => 'f'])->willReturn($family);
        $this->productRepository->existBy(['productFamilyUuid' => 'f'])->willReturn(true);
        $this->familyRepository->remove(Argument::any())->shouldNotBeCalled();

        $this->expectException(ProductFamilyHasProductsException::class);
        ($this->createHandler())(new RemoveProductFamilyMessage('f'));
    }
}
