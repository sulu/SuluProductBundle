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

/**
 * The slug a product or variant is linked by, per `sulu_product.variants.routing`.
 */
interface ProductSlugProviderInterface
{
    /**
     * @param string|null $code the variant code, for a dimension content that does not carry it
     */
    public function getSlug(ProductDimensionContentInterface $dimensionContent, ?string $code = null): ?string;

    /**
     * Whether a product of the type owns a route of its own, edited on its form.
     */
    public function ownsRoute(string $type): bool;
}
