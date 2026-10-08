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
 * Who owns the route of a product with variants, set by `sulu_product.variants.routing`.
 */
enum VariantRouting: string
{
    /** Each variant owns a route, the product with variants owns none. */
    case Route = 'route';

    /** The product with variants owns the route, a variant is reached via `?variant=<code>`. */
    case QueryParameter = 'query_parameter';

    public const QUERY_PARAMETER = 'variant';

    /**
     * Appends the variant to a product slug or URL.
     */
    public static function appendVariant(string $productPath, string $code): string
    {
        return $productPath . '?' . self::QUERY_PARAMETER . '=' . \rawurlencode($code);
    }
}
