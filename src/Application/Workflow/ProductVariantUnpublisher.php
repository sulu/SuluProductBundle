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

namespace Sulu\Product\Application\Workflow;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Takes the variants of a product offline in one locale: a variant renders its product's content,
 * so it cannot stay live where that product has no live content.
 *
 * @internal
 */
final class ProductVariantUnpublisher
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
        private readonly DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function unpublish(ProductInterface $product, string $locale): void
    {
        if (!$product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            return;
        }

        $variants = $this->productRepository->findBy(
            ['parent' => $product->getUuid()],
            [],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    'dimensionAttributes' => [
                        'locale' => $locale,
                        'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                    ],
                ],
            ],
        );

        foreach ($variants as $variant) {
            try {
                $this->contentWorkflow->apply($variant, ['locale' => $locale], WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH);
            } catch (UnavailableContentTransitionException|ContentNotFoundException) {
                continue; // not published in this locale
            }

            $this->domainEventCollector->collect(new ProductWorkflowTransitionAppliedEvent($variant, WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH, $locale));
        }
    }
}
