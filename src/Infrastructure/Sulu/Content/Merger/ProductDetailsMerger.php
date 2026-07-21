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

namespace Sulu\Product\Infrastructure\Sulu\Content\Merger;

use Sulu\Content\Application\ContentMerger\Merger\MergerInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

class ProductDetailsMerger implements MergerInterface
{
    public function merge(object $targetObject, object $sourceObject): void
    {
        if (!$targetObject instanceof ProductDimensionContentInterface) {
            return;
        }

        if (!$sourceObject instanceof ProductDimensionContentInterface) {
            return;
        }

        if (null !== $sourceObject->getTitle()) {
            $targetObject->setTitle($sourceObject->getTitle());
        }

        if (null !== $sourceObject->getCode()) {
            $targetObject->setCode($sourceObject->getCode());
        }

        if (null !== $sourceObject->getExternalIdentifier()) {
            $targetObject->setExternalIdentifier($sourceObject->getExternalIdentifier());
        }

        if (null !== $sourceObject->getProductFamily()) {
            $targetObject->setProductFamily($sourceObject->getProductFamily());
        }

        // Status lives only on the unlocalized dimension and is never null, so a
        // null-guard cannot detect "not set here"; take it from the unlocalized
        // dimension (locale === null) so a localized default cannot overwrite it.
        if (null === $sourceObject->getLocale()) {
            $targetObject->setStatus($sourceObject->getStatus());
        }

        $targetObject->setDetailsData(\array_merge(
            $targetObject->getDetailsData(),
            $sourceObject->getDetailsData(),
        ));
    }
}
