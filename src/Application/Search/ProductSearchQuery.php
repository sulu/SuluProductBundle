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

namespace Sulu\Product\Application\Search;

/**
 * A catalogue search. Field names are the index's field names (`status`, `attr_<key>`, `opt_<key>`).
 */
final class ProductSearchQuery
{
    /** Variants plus products without variants; a product that has variants is never a hit. */
    public const HITS_LEAVES = 'leaves';
    /** Products including variant parents, never a variant. */
    public const HITS_PRODUCTS = 'products';
    public const HITS_ALL = 'all';

    /**
     * @param array<string, string|string[]> $equals
     * @param array<string, array{min?: float, max?: float}> $ranges
     * @param string[] $countFacets
     * @param string[] $minMaxFacets
     * @param array<string, 'asc'|'desc'> $sortBy
     */
    public function __construct(
        public readonly string $locale,
        public readonly string $webspace,
        public readonly string $term = '',
        public readonly array $equals = [],
        public readonly array $ranges = [],
        public readonly array $countFacets = [],
        public readonly array $minMaxFacets = [],
        public readonly int $page = 1,
        public readonly int $limit = 24,
        public readonly string $hits = self::HITS_LEAVES,
        public readonly array $sortBy = [],
    ) {
        if (!\in_array($hits, [self::HITS_LEAVES, self::HITS_PRODUCTS, self::HITS_ALL], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown hit policy "%s".', $hits));
        }
        if ($page < 1 || $limit < 1) {
            throw new \InvalidArgumentException('Page and limit must be positive.');
        }
    }
}
