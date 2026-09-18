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

namespace Sulu\Product\Tests\Unit\Application\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Product\Application\Workflow\ProductVariantUnpublisher;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

#[CoversClass(ProductVariantUnpublisher::class)]
class ProductVariantUnpublisherTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentWorkflowInterface> */
    private ObjectProphecy $contentWorkflow;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ProductVariantUnpublisher $unpublisher;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentWorkflow = $this->prophesize(ContentWorkflowInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->unpublisher = new ProductVariantUnpublisher(
            $this->productRepository->reveal(),
            $this->contentWorkflow->reveal(),
            $this->domainEventCollector->reveal(),
        );
    }

    public function testUnpublishesThePublishedVariantsInTheLocale(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $publishedVariant = new Product('published-variant-uuid');
        $unpublishedVariant = new Product('unpublished-variant-uuid');

        $this->productRepository->findBy(['parent' => 'parent-uuid'], [], Argument::type('array'))
            ->willReturn([$publishedVariant, $unpublishedVariant]);

        $this->contentWorkflow->apply($publishedVariant, ['locale' => 'en'], 'unpublish')
            ->shouldBeCalledOnce()
            ->willReturn(new ProductDimensionContent($publishedVariant));
        $this->contentWorkflow->apply($unpublishedVariant, ['locale' => 'en'], 'unpublish')
            ->shouldBeCalledOnce()
            ->willThrow(new UnavailableContentTransitionException());

        $this->domainEventCollector->collect(Argument::that(
            fn (ProductWorkflowTransitionAppliedEvent $event) => 'published-variant-uuid' === $event->getResourceId(),
        ))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::that(
            fn (ProductWorkflowTransitionAppliedEvent $event) => 'unpublished-variant-uuid' === $event->getResourceId(),
        ))->shouldNotBeCalled();

        $this->unpublisher->unpublish($product, 'en');
    }

    public function testIgnoresAProductWithoutVariants(): void
    {
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();
        $this->contentWorkflow->apply(Argument::cetera())->shouldNotBeCalled();

        $this->unpublisher->unpublish(new Product('product-uuid'), 'en');
    }
}
