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
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Workflow\ProductVariantUnpublisher;
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
        private ProductVariantUnpublisher $variantUnpublisher,
    ) {
    }

    public function __invoke(ApplyWorkflowTransitionProductMessage $message): ProductInterface
    {
        $locale = $message->getLocale();
        $transitionName = $message->getTransitionName();

        $product = $this->productRepository->getOneBy(
            $message->getIdentifier(),
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    'dimensionAttributes' => [
                        'locale' => $locale,
                        'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                    ],
                ],
            ]
        );

        if (WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH === $transitionName) {
            $this->assertParentIsPublished($product, $locale);
        }

        $this->contentWorkflow->apply($product, ['locale' => $locale], $transitionName);

        $this->domainEventCollector->collect(new ProductWorkflowTransitionAppliedEvent($product, $transitionName, $locale));

        // publishing the product leaves its variants as they are
        if (WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH === $transitionName) {
            $this->variantUnpublisher->unpublish($product, $locale);
        }

        return $product;
    }

    /**
     * A variant renders its product's content, so it can only go live where that product is live.
     */
    private function assertParentIsPublished(ProductInterface $product, string $locale): void
    {
        $parent = $product->getParent();

        if (null === $parent) {
            return;
        }

        $publishedParents = $this->productRepository->countBy([
            'uuid' => $parent->getUuid(),
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]);

        if (0 === $publishedParents) {
            throw new UnavailableContentTransitionException(\sprintf('A variant can only be published while its product is published in locale "%s".', $locale));
        }
    }
}
