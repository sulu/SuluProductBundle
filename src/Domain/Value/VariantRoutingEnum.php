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

namespace Sulu\Product\Domain\Value;

use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Who owns the route of a product with variants, set by `sulu_product.variants.routing`.
 */
enum VariantRoutingEnum: string
{
    /** Each variant owns a route, the product with variants owns none. */
    case PER_VARIANT = 'per_variant';

    /** The product with variants owns the route, a variant is reached via `?<parameter>=<code>`. */
    case PER_PRODUCT = 'per_product';

    /**
     * Whether a product of the type owns a route of its own, edited on its form.
     */
    public function ownsRoute(string $type): bool
    {
        return match ($this) {
            self::PER_VARIANT => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS !== $type,
            self::PER_PRODUCT => ProductInterface::TYPE_VARIANT !== $type,
        };
    }
}
