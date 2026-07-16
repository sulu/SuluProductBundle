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

use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Flags a variant's parent as having unpublished changes whenever the variant is created or
 * modified — variants never publish on their own (see ApplyWorkflowTransitionProductMessageHandler).
 */
final class VariantParentDirtier
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
    ) {
    }

    public function markParentAsChanged(ProductInterface $product, string $locale): void
    {
        $parent = $product->getParent();
        if (null === $parent) {
            return;
        }

        $parentWithContent = $this->productRepository->getOneBy(
            ['uuid' => $parent->getUuid()],
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [],
                'dimensionAttributes' => [
                    'locale' => $locale,
                    'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                ],
            ]],
        );

        try {
            $this->contentWorkflow->apply(
                $parentWithContent,
                ['locale' => $locale],
                WorkflowInterface::WORKFLOW_TRANSITION_EDIT,
            );
        } catch (UnavailableContentTransitionException|ContentNotFoundException) {
            // `edit` isn't defined from every state (e.g. review/review_draft) or locale — a failed
            // dirty must never break the variant's own save.
            return;
        }
    }
}
