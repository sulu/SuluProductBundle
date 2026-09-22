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

namespace Sulu\Product\Infrastructure\Sulu\Route;

use Sulu\Content\Application\ContentLocalizationsResolver\ContentLocalizationsResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * A variant URL renders its product, but links the other locales to the variant's own URLs.
 *
 * @internal
 */
class ProductVariantLocalizationsResolver implements ContentLocalizationsResolverInterface
{
    public function __construct(
        private readonly ContentLocalizationsResolverInterface $inner,
        private readonly CurrentVariantProvider $currentVariantProvider,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, string $webspaceKey): array
    {
        $variantContent = $dimensionContent instanceof ProductDimensionContentInterface
            ? $this->currentVariantProvider->getCurrentVariant($dimensionContent)
            : null;

        if (null !== $variantContent) {
            return $this->inner->resolve($variantContent, $webspaceKey);
        }

        return $this->inner->resolve($dimensionContent, $webspaceKey);
    }
}
