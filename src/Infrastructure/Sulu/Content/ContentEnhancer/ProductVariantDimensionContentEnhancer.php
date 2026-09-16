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

namespace Sulu\Product\Infrastructure\Sulu\Content\ContentEnhancer;

use Sulu\Content\Application\ContentEnhancer\DimensionContentEnhancerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductParentContentLoader;

/**
 * Resolves a variant with its parent's template, excerpt and SEO data, since a variant has no content of its own.
 *
 * @internal
 */
class ProductVariantDimensionContentEnhancer implements DimensionContentEnhancerInterface
{
    public function __construct(
        private readonly ProductParentContentLoader $parentContentLoader,
    ) {
    }

    public function enhance(DimensionContentInterface $dimensionContent): DimensionContentInterface
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface) {
            return $dimensionContent;
        }

        $parentContent = $this->parentContentLoader->load($dimensionContent);
        if (null === $parentContent) {
            return $dimensionContent;
        }

        $templateKey = $parentContent->getTemplateKey();
        if (null !== $templateKey) {
            $dimensionContent->setTemplateKey($templateKey);
        }

        $dimensionContent->setTemplateData($parentContent->getTemplateData());

        $dimensionContent->setExcerptData($parentContent->getExcerptData());
        $dimensionContent->setExcerptTags($parentContent->getExcerptTags());
        $dimensionContent->setExcerptCategories($parentContent->getExcerptCategories());
        $dimensionContent->setExcerptAudienceTargetGroups($parentContent->getExcerptAudienceTargetGroups());
        $dimensionContent->setExcerptSegment($parentContent->getExcerptSegment());

        $dimensionContent->setSeoData($parentContent->getSeoData());
        $dimensionContent->setSeoNoIndex($parentContent->getSeoNoIndex());
        $dimensionContent->setSeoNoFollow($parentContent->getSeoNoFollow());
        $dimensionContent->setSeoHideInSitemap($parentContent->getSeoHideInSitemap());

        return $dimensionContent;
    }
}
