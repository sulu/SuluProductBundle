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
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Product\Application\Mapper\ProductContentMapper;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\MessageHandler\ModifyProductMessageHandler;
use Sulu\Product\Application\Workflow\VariantParentDirtier;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ModifyProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentPersisterInterface> */
    private ObjectProphecy $contentPersister;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ModifyProductMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentPersister = $this->prophesize(ContentPersisterInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $variantParentDirtier = new VariantParentDirtier(
            $this->productRepository->reveal(),
            $this->prophesize(ContentWorkflowInterface::class)->reveal(),
        );

        $this->handler = new ModifyProductMessageHandler(
            $this->productRepository->reveal(),
            [new ProductContentMapper($this->contentPersister->reveal())],
            $this->domainEventCollector->reveal(),
            $variantParentDirtier,
        );
    }

    public function testModifyProduct(): void
    {
        $product = new Product('prod-uuid');
        $data = ['locale' => 'en', 'code' => 'PROD-001'];

        $this->productRepository->getOneBy(
            Argument::that(fn (array $filters) => isset($filters['uuid']) && 'prod-uuid' === $filters['uuid']),
            Argument::type('array')
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->contentPersister->persist($product, Argument::type('array'), Argument::type('array'))
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductModifiedEvent::class))
            ->shouldBeCalledOnce();

        $message = new ModifyProductMessage(['uuid' => 'prod-uuid'], $data);

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
    }
}
