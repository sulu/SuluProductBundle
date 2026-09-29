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

namespace Sulu\Product\Infrastructure\Symfony\Ai\Tool;

use Sulu\Product\Application\Ai\AttributeFilter;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_search_products_by_attributes',
    description: 'Search published products by one or more specification attribute values, e.g. a rated current or a color — something sulu_product_get_products cannot do since it only matches product titles and codes, not specs. Call sulu_product_get_attributes first to get the exact attribute keys. Filters match literal values only, never a comparison or range: to look for the best match among several values, call this once per plausible literal value.',
)]
final class SearchProductsByAttributesTool
{
    public function __construct(
        private readonly SearchProductsByAttributes $searchProductsByAttributes,
    ) {
    }

    /**
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param list<AttributeFilter> $filters one to five filters ANDed together, "key" is the exact attribute key from sulu_product_get_attributes and "value" a literal value, a substring for text or options, an exact number for a number attribute, never a comparison, range or wildcard like "16A or more"
     * @param bool $includeVariants whether to include product variants in the results
     * @param int $limit maximum number of results to return, capped at 25
     *
     * @return array{
     *     results: list<array{code: string, title: string, productFamily: ?string, url: ?string}>,
     *     status: 'ok'|'no_match'|'unknown_attribute',
     *     instruction: ?string,
     * }
     */
    public function __invoke(
        string $locale,
        array $filters,
        bool $includeVariants = false,
        int $limit = 10,
    ): array {
        return ($this->searchProductsByAttributes)($locale, $filters, $includeVariants, $limit);
    }
}
