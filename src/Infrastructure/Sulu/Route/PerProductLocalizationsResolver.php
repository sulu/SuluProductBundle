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
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * The localizations of a product page. A variant has no route, so a locale in which the product has
 * a route and the variant is published links to the product's URL plus the variant parameter.
 *
 * @internal
 */
class PerProductLocalizationsResolver implements ContentLocalizationsResolverInterface
{
    public function __construct(
        private readonly ContentLocalizationsResolverInterface $routeLocalizationsResolver,
        private readonly CurrentVariantProvider $currentVariantProvider,
        private readonly VariantUrlBuilder $variantUrlBuilder,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, string $webspaceKey): array
    {
        $localizations = $this->routeLocalizationsResolver->resolve($dimensionContent, $webspaceKey);

        $variantContent = $dimensionContent instanceof ProductDimensionContentInterface
            ? $this->currentVariantProvider->getCurrentVariant($dimensionContent)
            : null;
        $code = $variantContent?->getCode();

        if (null === $variantContent || null === $code) {
            return $localizations;
        }

        $variantLocales = $variantContent->getAvailableLocales() ?? [];

        foreach ($localizations as $locale => $localization) {
            // a locale without a product route links the start page, a locale without the variant the product
            if (!$localization['alternate'] || !\in_array($locale, $variantLocales, true)) {
                continue;
            }

            $localizations[$locale]['url'] = $this->variantUrlBuilder->append($localization['url'], $code);
        }

        return $localizations;
    }
}
