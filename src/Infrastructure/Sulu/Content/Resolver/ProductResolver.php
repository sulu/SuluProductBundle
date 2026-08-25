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

/**
 * Assembles the root-level `product` namespace: the Details master-data fields flattened onto it,
 * plus `attributes`, `associations` and `variants`.
 *
 * A child that does not apply returns null and its key is omitted, which is how a referenced
 * product keeps its details while skipping attributes and variants.
 *
 * @internal
 */
class ProductResolver implements ResolverInterface
{
    public function __construct(
        private readonly ResolverInterface $detailsResolver,
        private readonly ResolverInterface $attributesResolver,
        private readonly ResolverInterface $associationsResolver,
        private readonly ResolverInterface $variantsResolver,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        $details = $this->detailsResolver->resolve($dimensionContent, $properties);

        if (!$details instanceof ContentView) {
            return null;
        }

        /** @var array<string, ContentView> $content */
        $content = $details->getContent();

        foreach ([
            'attributes' => $this->attributesResolver,
            'associations' => $this->associationsResolver,
            'variants' => $this->variantsResolver,
        ] as $key => $resolver) {
            $resolved = $resolver->resolve($dimensionContent, $properties);

            if (!$resolved instanceof ContentView) {
                continue;
            }

            $content[$key] = $resolved;
        }

        return ContentView::create($content, []);
    }
}
