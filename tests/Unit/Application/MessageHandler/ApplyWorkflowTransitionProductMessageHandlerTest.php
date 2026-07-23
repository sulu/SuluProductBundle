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
use Sulu\Product\Application\Workflow\VariantWorkflowCascader;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
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

        // VariantWorkflowCascader is final (cannot be prophesized); its own dependencies are
        // never exercised here since the test product is a plain (non-variant-parent) product.
        $variantWorkflowCascader = new VariantWorkflowCascader(
            $this->prophesize(ProductRepositoryInterface::class)->reveal(),
            $this->prophesize(ContentWorkflowInterface::class)->reveal(),
        );

        $this->handler = new ApplyWorkflowTransitionProductMessageHandler(
            $this->productRepository->reveal(),
            $this->contentWorkflow->reveal(),
            $this->domainEventCollector->reveal(),
            $variantWorkflowCascader,
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

    public function testApplyWorkflowTransitionCascadesToVariants(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage(DimensionContentInterface::STAGE_DRAFT);

        $this->productRepository->getOneBy(Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->contentWorkflow->apply($product, ['locale' => 'en'], 'publish')
            ->shouldBeCalledOnce()
            ->willReturn($dimensionContent);

        $this->domainEventCollector->collect(Argument::type(ProductWorkflowTransitionAppliedEvent::class))
            ->shouldBeCalledOnce();

        // A dedicated cascader whose repository reports no variants, so the cascade is invoked
        // (covering the parent-with-variants branch) without applying transitions to children.
        $cascaderRepository = $this->prophesize(ProductRepositoryInterface::class);
        $cascaderRepository->findBy(['parent' => 'parent-uuid'], Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn([]);

        $handler = new ApplyWorkflowTransitionProductMessageHandler(
            $this->productRepository->reveal(),
            $this->contentWorkflow->reveal(),
            $this->domainEventCollector->reveal(),
            new VariantWorkflowCascader(
                $cascaderRepository->reveal(),
                $this->prophesize(ContentWorkflowInterface::class)->reveal(),
            ),
        );

        $message = new ApplyWorkflowTransitionProductMessage(['uuid' => 'parent-uuid'], 'en', 'publish');

        $result = ($handler)($message);

        $this->assertSame($product, $result);
    }
}
