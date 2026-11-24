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

use Sulu\Product\Domain\Model\ProductInterface;

/**
 * The product with variants owns the route, a variant has none.
 *
 * @internal
 */
final class PerProductSlugProvider implements ProductSlugProviderInterface
{
    public function ownsRoute(string $type): bool
    {
        return ProductInterface::TYPE_VARIANT !== $type;
    }
}
