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

use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The variant the current request renders its product for: in `route` mode the variant whose route
 * matched, in `query_parameter` mode the published variant named by `?variant=<code>`.
 *
 * @internal
 */
class CurrentVariantProvider
{
    /** @var \WeakMap<Request, array<string, ProductDimensionContentInterface|null>> */
    private \WeakMap $loadedVariants;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentAggregatorInterface $contentAggregator,
        private readonly VariantRouting $routing,
    ) {
        $this->loadedVariants = new \WeakMap();
    }

    public function getCurrentVariant(ProductDimensionContentInterface $productContent): ?ProductDimensionContentInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $variantContent = VariantRouting::QueryParameter === $this->routing
            ? $this->loadVariant($request, $productContent)
            : $request->attributes->get(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE);

        if (!$variantContent instanceof ProductDimensionContentInterface
            || $variantContent->getResource()->getParent()?->getUuid() !== $productContent->getResource()->getUuid()
        ) {
            return null;
        }

        return $variantContent;
    }

    private function loadVariant(Request $request, ProductDimensionContentInterface $productContent): ?ProductDimensionContentInterface
    {
        // all() and not get(), which throws a 400 on `variant[]=x`
        $code = $request->query->all()[VariantRouting::QUERY_PARAMETER] ?? null;
        $locale = $productContent->getLocale();

        if (!\is_string($code) || '' === $code || null === $locale) {
            return null;
        }

        $productUuid = $productContent->getResource()->getUuid();
        $key = $productUuid . '__' . $locale . '__' . $code;
        $loaded = $this->loadedVariants[$request] ?? [];

        if (!\array_key_exists($key, $loaded)) {
            $loaded[$key] = $this->findLiveVariant($productUuid, $code, $locale);
            $this->loadedVariants[$request] = $loaded;
        }

        return $loaded[$key];
    }

    private function findLiveVariant(string $productUuid, string $code, string $locale): ?ProductDimensionContentInterface
    {
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        $variants = $this->productRepository->findBy(
            [
                'parent' => $productUuid,
                'code' => $code,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => ['dimensionAttributes' => $dimensionAttributes]],
        );

        foreach ($variants as $variant) {
            try {
                /** @var ProductDimensionContentInterface */
                return $this->contentAggregator->aggregate($variant, $dimensionAttributes);
            } catch (ContentNotFoundException) {
                return null;
            }
        }

        return null;
    }
}
