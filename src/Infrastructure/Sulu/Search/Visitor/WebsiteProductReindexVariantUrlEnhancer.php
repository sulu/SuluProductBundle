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

namespace Sulu\Product\Infrastructure\Sulu\Search\Visitor;

use Doctrine\ORM\QueryBuilder;
use Sulu\Product\Application\Routing\VariantUrlBuilder;

/**
 * A variant has no route of its own, so its document links to the route of its product plus the
 * variant parameter.
 *
 * @internal if you need to override this service, create a new service based on the WebsiteProductReindexProviderEnhancerInterface
 * instead of extending this class
 *
 * @final
 */
class WebsiteProductReindexVariantUrlEnhancer implements WebsiteProductReindexProviderEnhancerInterface
{
    public function __construct(
        private readonly VariantUrlBuilder $variantUrlBuilder,
    ) {
    }

    public function enhanceQuery(QueryBuilder $queryBuilder): void
    {
        $queryBuilder
            ->leftJoin(
                $queryBuilder->getRootEntities()[0],
                'parentDimensionContent',
                'WITH',
                'IDENTITY(parentDimensionContent.product) = IDENTITY(product.parent)'
                . ' AND parentDimensionContent.locale = dimensionContent.locale'
                . ' AND parentDimensionContent.stage = dimensionContent.stage'
                . ' AND parentDimensionContent.version = dimensionContent.version',
            )
            ->leftJoin('parentDimensionContent.route', 'parentRoute')
            ->addSelect('parentRoute.slug AS parentSlug');
    }

    public function enhanceDocument(array $queryResult, array $document): array
    {
        if (null === ($queryResult['parentId'] ?? null)) {
            return $document;
        }

        $parentSlug = $queryResult['parentSlug'] ?? null;
        $code = $queryResult['code'] ?? null;

        $document['url'] = \is_string($parentSlug) && \is_string($code) && '' !== $code
            ? $this->variantUrlBuilder->append($parentSlug, $code)
            : '';

        return $document;
    }
}
