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

namespace Sulu\Product\Infrastructure\Sulu\Content\Resolver;

use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Variant\ProductVariantLoader;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

/**
 * Exposes a product's variants under `product.variants`, as references resolved like any
 * product selection.
 *
 * Nothing is emitted when the content is itself being resolved as a reference — a product
 * selection on some other page — because a variant set is only ever read on the product's own
 * page, and loading one per referenced product multiplies the queries on every listing.
 *
 * @internal
 */
class ProductVariantsResolver implements ResolverInterface
{
    public function __construct(private readonly ProductVariantLoader $variantLoader)
    {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface || null !== $properties) {
            return null;
        }

        $product = $dimensionContent->getResource();

        if (!$product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            return null;
        }

        // merged content always carries a locale
        /** @var string $locale */
        $locale = $dimensionContent->getLocale();
        $uuids = $this->variantLoader->findVariantUuids($product, $locale, DimensionContentInterface::STAGE_LIVE);

        if ([] === $uuids) {
            return null;
        }

        return ContentView::createResolvablesWithReferences(
            ids: $uuids,
            resourceLoaderKey: ProductResourceLoader::getKey(),
            resourceKey: ProductInterface::RESOURCE_KEY,
            view: [],
            priority: 100,
            metadata: [
                'properties' => [
                    'title' => 'title',
                    'url' => 'url',
                    'code' => 'code',
                    'image' => 'image',
                ],
            ],
        );
    }
}
