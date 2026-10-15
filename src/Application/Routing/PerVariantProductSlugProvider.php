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
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Each variant is linked by its own route, a product with variants has none.
 *
 * @internal
 */
final class PerVariantProductSlugProvider implements ProductSlugProviderInterface
{
    public function getSlug(ProductDimensionContentInterface $dimensionContent, ?string $code = null): ?string
    {
        return $dimensionContent->getRoute()?->getSlug();
    }

    public function ownsRoute(string $type): bool
    {
        return ProductInterface::TYPE_PRODUCT_WITH_VARIANTS !== $type;
    }
}
