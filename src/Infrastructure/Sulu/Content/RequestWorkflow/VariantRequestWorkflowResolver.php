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

namespace Sulu\Product\Infrastructure\Sulu\Content\RequestWorkflow;

use Sulu\Content\Application\RequestWorkflow\RequestWorkflow;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * A variant has no SEO or excerpt tab and does not show the product template's own fields, so the workflow's SEO,
 * excerpt and publish-required rules can never be met on it. It keeps the rest of its product's workflow, including
 * rules a project adds.
 *
 * @internal
 */
final class VariantRequestWorkflowResolver implements RequestWorkflowResolverInterface
{
    private const UNMEETABLE_PRE_VALIDATORS = ['seo_required', 'excerpt_required', 'publish_required'];

    public function __construct(
        private readonly RequestWorkflowResolverInterface $inner,
    ) {
    }

    public function resolveForContent(DimensionContentInterface $dimensionContent): ?RequestWorkflow
    {
        $workflow = $this->inner->resolveForContent($dimensionContent);

        if (null === $workflow
            || !$dimensionContent instanceof ProductDimensionContentInterface
            || null === $dimensionContent->getResource()->getParent()
        ) {
            return $workflow;
        }

        return new RequestWorkflow(
            $workflow->name,
            $workflow->validators,
            $workflow->getRequiredUserApprovals(),
            $workflow->resources,
            \array_diff_key($workflow->preValidators, \array_flip(self::UNMEETABLE_PRE_VALIDATORS)),
        );
    }

    public function resolveTemplateKeysWithWorkflow(string $resourceKey, string $templateType): array
    {
        return $this->inner->resolveTemplateKeysWithWorkflow($resourceKey, $templateType);
    }
}
