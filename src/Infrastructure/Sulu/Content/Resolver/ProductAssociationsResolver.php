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

namespace Sulu\Product\Infrastructure\Sulu\Content\Resolver;

use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductSelectionPropertyResolver;

/**
 * Exposes the product's association targets to the frontend under `extension.associations.*`.
 *
 * @internal
 */
class ProductAssociationsResolver implements ResolverInterface
{
    public function __construct(
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
        private readonly ProductSelectionPropertyResolver $productSelectionPropertyResolver,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface) {
            return null;
        }

        // merged content always carries a locale
        /** @var string $locale */
        $locale = $dimensionContent->getLocale();

        $map = [];
        foreach ($this->associationTypeRegistry->getTypes() as $type) {
            $uuids = \array_map(
                static fn (ProductAssociationInterface $association): string => $association->getTarget()->getUuid(),
                $dimensionContent->getAssociationsByType($type->getKey()),
            );

            // delegates to inherit priority 100 + metadata.properties (title, url), preventing recursive full-product resolution
            $map[$type->getKey()] = $this->productSelectionPropertyResolver->resolve($uuids, $locale);
        }

        return ContentView::create($map, []);
    }
}
