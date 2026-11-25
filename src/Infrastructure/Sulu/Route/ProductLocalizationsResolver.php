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
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Value\VariantRoutingEnum;

/**
 * The localizations of a product page: a variant that owns a route links the other locales to its
 * own URLs, otherwise the product's URLs are linked.
 *
 * @internal
 */
class ProductLocalizationsResolver implements ContentLocalizationsResolverInterface
{
    public function __construct(
        private readonly ContentLocalizationsResolverInterface $routeLocalizationsResolver,
        private readonly CurrentVariantProvider $currentVariantProvider,
        private readonly VariantRoutingEnum $variantRouting,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, string $webspaceKey): array
    {
        $variantContent = $dimensionContent instanceof ProductDimensionContentInterface
            && $this->variantRouting->ownsRoute(ProductInterface::TYPE_VARIANT)
            ? $this->currentVariantProvider->getCurrentVariant($dimensionContent)
            : null;

        if (null !== $variantContent) {
            return $this->routeLocalizationsResolver->resolve($variantContent, $webspaceKey);
        }

        return $this->routeLocalizationsResolver->resolve($dimensionContent, $webspaceKey);
    }
}
