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

namespace Sulu\Product\Domain\Model;

/**
 * Which family attributes a product holds by its type: a variant only variant attributes, a product
 * with variants only shared ones, a product without variants both, since nothing else holds its
 * variant attributes.
 */
final class ProductAttributeScope
{
    public static function holds(string $productType, ProductFamilyAttributeInterface $familyAttribute): bool
    {
        return match ($productType) {
            ProductInterface::TYPE_VARIANT => $familyAttribute->isVariantSpecific(),
            ProductInterface::TYPE_PRODUCT_WITH_VARIANTS => !$familyAttribute->isVariantSpecific(),
            default => true,
        };
    }
}
