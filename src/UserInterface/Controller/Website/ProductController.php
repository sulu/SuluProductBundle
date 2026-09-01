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

namespace Sulu\Product\UserInterface\Controller\Website;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\UserInterface\Controller\Website\ContentController;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the `?variant=` selection once, so the page, its canonical and its hreflang set cannot
 * disagree about which variant is shown.
 *
 * @extends ContentController<ProductDimensionContentInterface>
 */
class ProductController extends ContentController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $variantQueryParameter,
    ) {
    }

    /**
     * @param ProductDimensionContentInterface $object
     *
     * @return array<string, mixed>
     */
    protected function resolveSuluParameters(DimensionContentInterface $object, string $webspaceKey, bool $normalize): array
    {
        $parameters = parent::resolveSuluParameters($object, $webspaceKey, $normalize);

        $product = $parameters['product'] ?? null;
        $resolvedVariants = \is_array($product) ? ($product['variants'] ?? null) : null;

        /** @var list<array<string, mixed>> $variants */
        $variants = \is_array($resolvedVariants) ? \array_values($resolvedVariants) : [];

        /** @var array<array-key, array<string, mixed>> $localizations */
        $localizations = \is_array($parameters['localizations'] ?? null) ? $parameters['localizations'] : [];

        $selected = $this->selectVariant($variants);

        $parameters['selectedVariant'] = $selected;
        $parameters['localizations'] = $this->localizeSelection($localizations, $selected);

        return $parameters;
    }

    /**
     * @param list<array<string, mixed>> $variants
     *
     * @return array<string, mixed>|null the variant the query names, the first one for an unknown
     *                                   or missing code, null without variants
     */
    public function selectVariant(array $variants): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Through all(): get() throws a 400 on `?variant[]=x`, which should fall back instead.
        $code = $request?->query->all()[$this->variantQueryParameter] ?? null;

        if (\is_string($code) && '' !== $code) {
            foreach ($variants as $variant) {
                $product = $variant['product'] ?? null;

                if (\is_array($product) && ($product['code'] ?? null) === $code) {
                    return $variant;
                }
            }
        }

        return $variants[0] ?? null;
    }

    /**
     * Carries the selection into every locale that publishes the variant. The rest keep the bare
     * URL and leave hreflang, where the code would show the default variant instead.
     *
     * @param array<array-key, array<string, mixed>> $localizations
     * @param array<string, mixed>|null $selected
     *
     * @return array<array-key, array<string, mixed>>
     */
    public function localizeSelection(array $localizations, ?array $selected): array
    {
        $query = $this->variantQuery($selected);

        if ('' === $query) {
            return $localizations;
        }

        $available = $selected['availableLocales'] ?? null;
        $available = \is_array($available) ? $available : [];

        foreach ($localizations as $key => $localization) {
            $locale = $localization['locale'] ?? $key;
            $url = $localization['url'] ?? null;

            if (!\is_string($url) || !\in_array($locale, $available, true)) {
                $localizations[$key]['alternate'] = false;

                continue;
            }

            $localizations[$key]['url'] = $url . $query;
        }

        return $localizations;
    }

    /**
     * The `?variant=` of the selection, empty for the default variant and for a product without.
     *
     * @param array<string, mixed>|null $selected
     */
    private function variantQuery(?array $selected): string
    {
        $product = $selected['product'] ?? null;
        $position = \is_array($product) ? ($product['position'] ?? null) : null;
        $code = \is_array($product) ? ($product['code'] ?? null) : null;

        // Position 0 is the bare URL.
        if (!\is_int($position) || 0 === $position || !\is_string($code) || '' === $code) {
            return '';
        }

        return '?' . \http_build_query([$this->variantQueryParameter => $code]);
    }
}
