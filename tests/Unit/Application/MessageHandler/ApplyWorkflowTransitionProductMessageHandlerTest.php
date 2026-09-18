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
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\MessageHandler\ApplyWorkflowTransitionProductMessageHandler;
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

    public function testApplyWorkflowTransitionOnAParentLeavesItsVariantsAlone(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage(DimensionContentInterface::STAGE_DRAFT);

        $this->productRepository->getOneBy(Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn($product);
        $this->productRepository->findBy(Argument::cetera())
            ->shouldNotBeCalled();

        $this->contentWorkflow->apply($product, ['locale' => 'en'], 'publish')
            ->shouldBeCalledOnce()
            ->willReturn($dimensionContent);

        $this->domainEventCollector->collect(Argument::type(ProductWorkflowTransitionAppliedEvent::class))
            ->shouldBeCalledOnce();

        $message = new ApplyWorkflowTransitionProductMessage(['uuid' => 'parent-uuid'], 'en', 'publish');

        $this->assertSame($product, ($this->handler)($message));
    }

    public function testUnpublishingAParentUnpublishesItsVariants(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $publishedVariant = new Product('published-variant-uuid');
        $unpublishedVariant = new Product('unpublished-variant-uuid');

        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'], Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn($product);
        $this->productRepository->findBy(['parent' => 'parent-uuid'], [], Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn([$publishedVariant, $unpublishedVariant]);

        $this->contentWorkflow->apply($product, ['locale' => 'en'], 'unpublish')
            ->shouldBeCalledOnce()
            ->willReturn(new ProductDimensionContent($product));
        $this->contentWorkflow->apply($publishedVariant, ['locale' => 'en'], 'unpublish')
            ->shouldBeCalledOnce()
            ->willReturn(new ProductDimensionContent($publishedVariant));
        $this->contentWorkflow->apply($unpublishedVariant, ['locale' => 'en'], 'unpublish')
            ->shouldBeCalledOnce()
            ->willThrow(new UnavailableContentTransitionException());

        $this->domainEventCollector->collect(Argument::that(
            fn (ProductWorkflowTransitionAppliedEvent $event) => 'parent-uuid' === $event->getResourceId(),
        ))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::that(
            fn (ProductWorkflowTransitionAppliedEvent $event) => 'published-variant-uuid' === $event->getResourceId(),
        ))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::that(
            fn (ProductWorkflowTransitionAppliedEvent $event) => 'unpublished-variant-uuid' === $event->getResourceId(),
        ))->shouldNotBeCalled();

        $message = new ApplyWorkflowTransitionProductMessage(['uuid' => 'parent-uuid'], 'en', 'unpublish');

        $this->assertSame($product, ($this->handler)($message));
    }

    public function testPublishingAVariantOfAnUnpublishedProductIsRefused(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'], Argument::type('array'))
            ->willReturn($variant);
        $this->productRepository->countBy(['uuid' => 'parent-uuid', 'locale' => 'en', 'stage' => 'live'])
            ->shouldBeCalledOnce()
            ->willReturn(0);

        $this->contentWorkflow->apply(Argument::cetera())->shouldNotBeCalled();
        $this->domainEventCollector->collect(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(UnavailableContentTransitionException::class);

        ($this->handler)(new ApplyWorkflowTransitionProductMessage(['uuid' => 'variant-uuid'], 'en', 'publish'));
    }

    public function testPublishingAVariantOfAPublishedProduct(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'], Argument::type('array'))
            ->willReturn($variant);
        $this->productRepository->countBy(['uuid' => 'parent-uuid', 'locale' => 'en', 'stage' => 'live'])
            ->willReturn(1);

        $this->contentWorkflow->apply($variant, ['locale' => 'en'], 'publish')
            ->shouldBeCalledOnce()
            ->willReturn(new ProductDimensionContent($variant));
        $this->domainEventCollector->collect(Argument::type(ProductWorkflowTransitionAppliedEvent::class))
            ->shouldBeCalledOnce();

        $this->assertSame($variant, ($this->handler)(new ApplyWorkflowTransitionProductMessage(['uuid' => 'variant-uuid'], 'en', 'publish')));
    }
}
