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
 * Who owns the route of a product, per `sulu_product.variants.routing`.
 */
interface ProductSlugProviderInterface
{
    /**
     * Whether a product of the type owns a route of its own, edited on its form.
     */
    public function ownsRoute(string $type): bool;
}
