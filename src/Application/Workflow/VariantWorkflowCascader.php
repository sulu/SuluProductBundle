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
 * Cascades a parent's publish/unpublish transition to each of its variants. Re-applying `publish`
 * to an already-published variant does NOT throw (`publish` is defined `published -> published`);
 * the catch below exists for `unpublish` on a never-published variant and missing-locale content.
 *
 * @internal
 */
final class VariantWorkflowCascader
{
    private const CASCADED_TRANSITIONS = [
        WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
        WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentWorkflowInterface $contentWorkflow,
    ) {
    }

    public function cascade(ProductInterface $parent, string $transitionName, string $locale): void
    {
        if (!\in_array($transitionName, self::CASCADED_TRANSITIONS, true)) {
            return;
        }

        $variants = $this->productRepository->findBy(
            ['parent' => $parent->getUuid()],
            [],
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [],
                'dimensionAttributes' => [
                    'locale' => $locale,
                    'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                ],
            ]],
        );

        foreach ($variants as $variant) {
            try {
                $this->contentWorkflow->apply($variant, ['locale' => $locale], $transitionName);
            } catch (UnavailableContentTransitionException|ContentNotFoundException) {
                continue;
            }
        }
    }
}
