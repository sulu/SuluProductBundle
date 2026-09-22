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

namespace Sulu\Product\Infrastructure\Sulu\Route;

use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The variant whose URL the current request renders its product for.
 *
 * @internal
 */
class CurrentVariantProvider
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCurrentVariant(ProductDimensionContentInterface $productContent): ?ProductDimensionContentInterface
    {
        $variantContent = $this->requestStack->getCurrentRequest()?->attributes->get(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE);

        if (!$variantContent instanceof ProductDimensionContentInterface
            || $variantContent->getResource()->getParent()?->getUuid() !== $productContent->getResource()->getUuid()
        ) {
            return null;
        }

        return $variantContent;
    }
}
