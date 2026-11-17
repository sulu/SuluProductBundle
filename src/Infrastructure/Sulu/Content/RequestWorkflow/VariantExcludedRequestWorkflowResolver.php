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
 * A variant is only published and unpublished from its overlay, which has no SEO or excerpt tab, so the
 * workflow's SEO and excerpt rules can never be met on it. It resolves to no workflow; its product keeps its own.
 *
 * @internal
 */
final class VariantExcludedRequestWorkflowResolver implements RequestWorkflowResolverInterface
{
    public function __construct(
        private readonly RequestWorkflowResolverInterface $inner,
    ) {
    }

    public function resolveForContent(DimensionContentInterface $dimensionContent): ?RequestWorkflow
    {
        if ($dimensionContent instanceof ProductDimensionContentInterface
            && null !== $dimensionContent->getResource()->getParent()
        ) {
            return null;
        }

        return $this->inner->resolveForContent($dimensionContent);
    }

    public function resolveTemplateKeysWithWorkflow(string $resourceKey, string $templateType): array
    {
        return $this->inner->resolveTemplateKeysWithWorkflow($resourceKey, $templateType);
    }
}
