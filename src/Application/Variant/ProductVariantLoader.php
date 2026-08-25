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

namespace Sulu\Product\Application\Variant;

use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Loads a product's variants as merged dimension content.
 *
 * Variants are not routable, so a project addresses them by code rather than by URL.
 *
 * @internal
 *
 * @final
 */
readonly class ProductVariantLoader
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentAggregatorInterface $contentAggregator,
    ) {
    }

    /**
     * @return list<ProductDimensionContentInterface>
     */
    public function findVariants(ProductInterface $product, string $locale, string $stage): array
    {
        if (!$product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            return [];
        }

        $variants = [];
        foreach ($this->loadVariants($product, $locale, $stage) as $variant) {
            $content = $this->aggregate($variant, $locale, $stage);

            if ($content instanceof ProductDimensionContentInterface) {
                $variants[] = $content;
            }
        }

        return $variants;
    }

    /**
     * Without SELECT_PRODUCT_CONTENT: selecting the dimension contents hydrates them filtered by
     * this stage, and a later aggregate() at another stage finds nothing on the same entity.
     *
     * @return list<string>
     */
    public function findVariantUuids(ProductInterface $product, string $locale, string $stage): array
    {
        if (!$product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            return [];
        }

        $uuids = [];
        foreach ($this->productRepository->findBy(
            ['parent' => $product->getUuid(), 'locale' => $locale, 'stage' => $stage],
            ['created' => 'asc', 'uuid' => 'asc'],
        ) as $variant) {
            $uuids[] = $variant->getUuid();
        }

        return $uuids;
    }

    public function findVariantByCode(
        ProductInterface $product,
        string $code,
        string $locale,
        string $stage,
    ): ?ProductDimensionContentInterface {
        foreach ($this->findVariants($product, $locale, $stage) as $variant) {
            if ($variant->getCode() === $code) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Loaded through the repository rather than the entity graph: the aggregator requires
     * dimension contents to be eager-loaded and throws in debug mode when they are not.
     *
     * @return iterable<ProductInterface>
     */
    private function loadVariants(ProductInterface $product, string $locale, string $stage): iterable
    {
        return $this->productRepository->findBy(
            ['parent' => $product->getUuid(), 'locale' => $locale, 'stage' => $stage],
            // The repository honours only `uuid` and `created`; `uuid` also breaks the ties.
            ['created' => 'asc', 'uuid' => 'asc'],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
                ],
            ],
        );
    }

    private function aggregate(
        ProductInterface $product,
        string $locale,
        string $stage,
    ): ?ProductDimensionContentInterface {
        try {
            return $this->contentAggregator->aggregate($product, ['locale' => $locale, 'stage' => $stage]);
        } catch (ContentNotFoundException) {
            // a variant with no content in this locale is simply not one of them
            return null;
        }
    }
}
