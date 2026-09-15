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

namespace Sulu\Product\Application\Attribute;

use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

/**
 * Combines the attribute values of a product with those of one of its variants, keyed by attribute
 * key; a variant's value wins over the product's.
 */
class ProductVariantAttributesMerger
{
    /**
     * @param iterable<ProductAttributeValueInterface> $productAttributes
     * @param iterable<ProductAttributeValueInterface> $variantAttributes
     *
     * @return array<string, ProductAttributeValueInterface>
     */
    public function merge(iterable $productAttributes, iterable $variantAttributes): array
    {
        $merged = [];

        foreach ([$productAttributes, $variantAttributes] as $attributes) {
            foreach ($attributes as $value) {
                $merged[$value->getAttribute()->getKey()] = $value;
            }
        }

        return $merged;
    }
}
