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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Activity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ActivityBundle\Application\Dispatcher\DomainEventDispatcherInterface;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequest;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionRequestEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Activity\ProductWorkflowTransitionRequestSubscriber;

#[CoversClass(ProductWorkflowTransitionRequestSubscriber::class)]
class ProductWorkflowTransitionRequestSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<ProductRepositoryInterface>
     */
    private ObjectProphecy $productRepository;

    /**
     * @var ObjectProphecy<DomainEventCollectorInterface>
     */
    private ObjectProphecy $domainEventCollector;

    /**
     * @var ObjectProphecy<DomainEventDispatcherInterface>
     */
    private ObjectProphecy $domainEventDispatcher;

    private ProductWorkflowTransitionRequestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
        $this->domainEventDispatcher = $this->prophesize(DomainEventDispatcherInterface::class);

        $this->subscriber = new ProductWorkflowTransitionRequestSubscriber(
            $this->productRepository->reveal(),
            $this->domainEventCollector->reveal(),
            $this->domainEventDispatcher->reveal(),
        );
    }

    public function testSubscribesToTheActionEvent(): void
    {
        $this->assertArrayHasKey(WorkflowTransitionRequestActionEvent::class, ProductWorkflowTransitionRequestSubscriber::getSubscribedEvents());
    }

    public function testCollectsTheDomainEventForItsResourceKey(): void
    {
        $article = $this->prophesize(ProductInterface::class)->reveal();
        $this->productRepository->findOneBy(['uuid' => 'resource-1'], Argument::type('array'))->willReturn($article);

        $this->domainEventCollector->collect(Argument::that(
            static fn (object $event) => $event instanceof ProductWorkflowTransitionRequestEvent
                && $article === $event->getProduct()
                && 'workflow_transition_request.approved' === $event->getEventType()
                && 'de' === $event->getResourceLocale()
                && ['comment' => 'Fine'] === $event->getEventContext(),
        ))->shouldBeCalledOnce();

        $this->subscriber->onWorkflowTransitionRequestAction(new WorkflowTransitionRequestActionEvent(
            new WorkflowTransitionRequest(ProductInterface::RESOURCE_KEY, 'resource-1', 'de', 'default'),
            WorkflowTransitionRequestActionEvent::APPROVED,
            ['comment' => 'Fine'],
        ));
    }

    public function testDispatchesValidatedRightAwayInsteadOfCollectingIt(): void
    {
        $article = $this->prophesize(ProductInterface::class)->reveal();
        $this->productRepository->findOneBy(['uuid' => 'resource-1'], Argument::type('array'))->willReturn($article);

        $this->domainEventDispatcher->dispatch(Argument::that(
            static fn (object $event) => $event instanceof ProductWorkflowTransitionRequestEvent
                && 'workflow_transition_request.validated' === $event->getEventType()
                && ['approved' => 1, 'rejected' => 2] === $event->getEventContext(),
        ))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::any())->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransitionRequestAction(new WorkflowTransitionRequestActionEvent(
            new WorkflowTransitionRequest(ProductInterface::RESOURCE_KEY, 'resource-1', 'de', 'default'),
            WorkflowTransitionRequestActionEvent::VALIDATED,
            ['approved' => 1, 'rejected' => 2],
        ));
    }

    public function testIgnoresOtherResourceKeys(): void
    {
        $this->productRepository->findOneBy(Argument::cetera())->shouldNotBeCalled();
        $this->domainEventCollector->collect(Argument::any())->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransitionRequestAction(new WorkflowTransitionRequestActionEvent(
            new WorkflowTransitionRequest('other', 'resource-1', 'de', 'default'),
            WorkflowTransitionRequestActionEvent::APPROVED,
            [],
        ));
    }

    public function testCollectsNothingWhenTheContentIsGone(): void
    {
        $this->productRepository->findOneBy(Argument::cetera())->willReturn(null);
        $this->domainEventCollector->collect(Argument::any())->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransitionRequestAction(new WorkflowTransitionRequestActionEvent(
            new WorkflowTransitionRequest(ProductInterface::RESOURCE_KEY, 'resource-1', 'de', 'default'),
            WorkflowTransitionRequestActionEvent::VALIDATED,
            ['approved' => 1, 'rejected' => 0],
        ));
    }
}
