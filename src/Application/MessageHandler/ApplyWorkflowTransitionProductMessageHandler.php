<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Workflow\VariantWorkflowCascader;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create your own Message and Handler instead.
 */
final class ApplyWorkflowTransitionProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentWorkflowInterface $contentWorkflow,
        private DomainEventCollectorInterface $domainEventCollector,
        private VariantWorkflowCascader $variantWorkflowCascader,
    ) {
    }

    public function __invoke(ApplyWorkflowTransitionProductMessage $message): ProductInterface
    {
        $product = $this->productRepository->getOneBy(
            $message->getIdentifier(),
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    'dimensionAttributes' => [
                        'locale' => $message->getLocale(),
                        'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                    ],
                ],
            ]
        );

        $this->contentWorkflow->apply(
            $product,
            ['locale' => $message->getLocale()],
            $message->getTransitionName()
        );

        if ($product->isVariantProduct()) {
            $this->variantWorkflowCascader->cascade($product, $message->getTransitionName(), $message->getLocale());
        }

        $this->domainEventCollector->collect(new ProductWorkflowTransitionAppliedEvent($product, $message->getTransitionName(), $message->getLocale()));

        return $product;
    }
}
