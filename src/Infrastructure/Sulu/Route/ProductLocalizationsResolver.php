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
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Application\Routing\VariantSlugResolver;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * The localizations of a product page. A variant URL renders its product, but links the other
 * locales to the variant: in `route` mode to the variant's own URLs, in `query_parameter` mode to
 * the product's URLs plus `?variant=<code>` where the variant is published.
 *
 * @internal
 */
class ProductLocalizationsResolver implements ContentLocalizationsResolverInterface
{
    public function __construct(
        private readonly ContentLocalizationsResolverInterface $routeLocalizationsResolver,
        private readonly CurrentVariantProvider $currentVariantProvider,
        private readonly VariantSlugResolver $variantSlugResolver,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, string $webspaceKey): array
    {
        $variantContent = $dimensionContent instanceof ProductDimensionContentInterface
            ? $this->currentVariantProvider->getCurrentVariant($dimensionContent)
            : null;

        if (null !== $variantContent && !$this->variantSlugResolver->isQueryParameter()) {
            return $this->routeLocalizationsResolver->resolve($variantContent, $webspaceKey);
        }

        $localizations = $this->routeLocalizationsResolver->resolve($dimensionContent, $webspaceKey);
        if (null === $variantContent) {
            return $localizations;
        }

        $code = $variantContent->getCode();
        $variantLocales = $variantContent->getAvailableLocales() ?? [];

        foreach ($localizations as $locale => $localization) {
            // a locale without a product route links the start page, a locale without the variant the product
            if (null === $code || !$localization['alternate'] || !\in_array($locale, $variantLocales, true)) {
                continue;
            }

            $localizations[$locale]['url'] = VariantRouting::appendVariant($localization['url'], $code);
        }

        return $localizations;
    }
}
