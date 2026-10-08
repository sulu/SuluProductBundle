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

namespace Sulu\Product\Application\Routing;

use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The slug a product is linked by. In `query_parameter` mode a variant is linked by its product's
 * slug plus `?variant=<code>`; the slug is appended verbatim when a URL is generated from it.
 *
 * @internal
 */
class VariantSlugResolver implements ResetInterface
{
    /** @var array<string, string|null> keyed by "<productUuid>__<locale>" */
    private array $productSlugs = [];

    public function __construct(
        private readonly RouteRepositoryInterface $routeRepository,
        private readonly VariantRouting $routing,
    ) {
    }

    public function isQueryParameter(): bool
    {
        return VariantRouting::QueryParameter === $this->routing;
    }

    /**
     * @param string|null $code the variant code, for a dimension content that does not carry it
     */
    public function resolve(ProductDimensionContentInterface $dimensionContent, ?string $code = null): ?string
    {
        if (!$this->isQueryParameter()) {
            return $dimensionContent->getRoute()?->getSlug();
        }

        $parent = $dimensionContent->getResource()->getParent();
        $locale = $dimensionContent->getLocale();

        if (null === $parent || null === $locale) {
            return $dimensionContent->getRoute()?->getSlug();
        }

        $code ??= $dimensionContent->getCode();

        return null === $code ? null : $this->resolveVariant($parent->getUuid(), $code, $locale);
    }

    /**
     * The slug of a variant in `query_parameter` mode, null when its product has no route.
     */
    public function resolveVariant(string $productUuid, string $code, string $locale): ?string
    {
        $productSlug = $this->getProductSlug($productUuid, $locale);

        return null === $productSlug ? null : VariantRouting::appendVariant($productSlug, $code);
    }

    public function reset(): void
    {
        $this->productSlugs = [];
    }

    private function getProductSlug(string $productUuid, string $locale): ?string
    {
        $key = $productUuid . '__' . $locale;

        if (!\array_key_exists($key, $this->productSlugs)) {
            $this->productSlugs[$key] = $this->routeRepository->findFirstBy([
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => $productUuid,
                'locale' => $locale,
            ])?->getSlug();
        }

        return $this->productSlugs[$key];
    }
}
