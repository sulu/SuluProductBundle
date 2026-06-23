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
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Product\Application\Mapper\ProductMapperInterface;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\MessageHandler\ModifyProductMessageHandler;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ModifyProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ProductMapperInterface> */
    private ObjectProphecy $productMapper;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ModifyProductMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->productMapper = $this->prophesize(ProductMapperInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new ModifyProductMessageHandler(
            $this->productRepository->reveal(),
            [$this->productMapper->reveal()],
            $this->domainEventCollector->reveal(),
        );
    }

    public function testModifyProduct(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');
        $data = ['locale' => 'en', 'code' => 'PROD-001'];

        $this->productRepository->getOneBy(
            Argument::that(fn (array $filters) => isset($filters['locale']) && 'en' === $filters['locale']),
            Argument::type('array')
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->existBy(['code' => 'PROD-001'])
            ->willReturn(false);

        $this->productMapper->mapProductData($product, $data)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductModifiedEvent::class))
            ->shouldBeCalledOnce();

        $message = new ModifyProductMessage(['uuid' => 'prod-uuid'], $data);

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
    }

    public function testModifyProductThrowsOnDuplicateCode(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');
        $data = ['locale' => 'en', 'code' => 'TAKEN-CODE'];

        $this->productRepository->getOneBy(
            Argument::that(fn (array $filters) => isset($filters['locale']) && 'en' === $filters['locale']),
            Argument::type('array')
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->existBy(['code' => 'TAKEN-CODE'])
            ->willReturn(true);

        $message = new ModifyProductMessage(['uuid' => 'prod-uuid'], $data);

        $this->expectException(ProductCodeNotUniqueException::class);

        ($this->handler)($message);
    }
}
