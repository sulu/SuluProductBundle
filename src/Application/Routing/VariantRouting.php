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

/**
 * Who owns the route of a product with variants, set by `sulu_product.variants.routing`. Only the
 * bundle extension reads it, to register the matching implementations.
 */
enum VariantRouting: string
{
    /** Each variant owns a route, the product with variants owns none. */
    case PerVariant = 'per_variant';

    /** The product with variants owns the route, a variant is reached via `?<parameter>=<code>`. */
    case PerProduct = 'per_product';
}
