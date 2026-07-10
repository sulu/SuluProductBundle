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
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\MessageHandler\ApplyWorkflowTransitionProductMessageHandler;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ApplyWorkflowTransitionProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentWorkflowInterface> */
    private ObjectProphecy $contentWorkflow;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ApplyWorkflowTransitionProductMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentWorkflow = $this->prophesize(ContentWorkflowInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new ApplyWorkflowTransitionProductMessageHandler(
            $this->productRepository->reveal(),
            $this->contentWorkflow->reveal(),
            $this->domainEventCollector->reveal(),
        );
    }

    public function testApplyWorkflowTransition(): void
    {
        $product = new Product('prod-uuid');

        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage(DimensionContentInterface::STAGE_DRAFT);

        $this->productRepository->getOneBy(
            Argument::that(fn (array $filters) => isset($filters['uuid']) && 'prod-uuid' === $filters['uuid']),
            Argument::type('array')
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->contentWorkflow->apply(
            $product,
            ['locale' => 'en'],
            'publish'
        )
            ->shouldBeCalledOnce()
            ->willReturn($dimensionContent);

        $this->domainEventCollector->collect(Argument::type(ProductWorkflowTransitionAppliedEvent::class))
            ->shouldBeCalledOnce();

        $message = new ApplyWorkflowTransitionProductMessage(['uuid' => 'prod-uuid'], 'en', 'publish');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
    }
}
