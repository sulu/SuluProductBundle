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
 * A variant is linked by the slug of its product plus `?<parameter>=<code>`, which is appended
 * verbatim when a URL is generated from it.
 *
 * @internal
 */
final class PerProductSlugProvider implements ProductSlugProviderInterface, ResetInterface
{
    /** @var array<string, string|null> keyed by "<productUuid>__<locale>" */
    private array $productSlugs = [];

    public function __construct(
        private readonly RouteRepositoryInterface $routeRepository,
        private readonly VariantUrlBuilder $variantUrlBuilder,
    ) {
    }

    public function getSlug(ProductDimensionContentInterface $dimensionContent, ?string $code = null): ?string
    {
        $parent = $dimensionContent->getResource()->getParent();
        $locale = $dimensionContent->getLocale();

        if (null === $parent || null === $locale) {
            return $dimensionContent->getRoute()?->getSlug();
        }

        $code ??= $dimensionContent->getCode();
        if (null === $code) {
            return null;
        }

        $productSlug = $this->getProductSlug($parent->getUuid(), $locale);

        return null === $productSlug ? null : $this->variantUrlBuilder->append($productSlug, $code);
    }

    public function ownsRoute(string $type): bool
    {
        return ProductInterface::TYPE_VARIANT !== $type;
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
