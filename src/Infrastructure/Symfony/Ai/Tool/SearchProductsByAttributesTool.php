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

use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_search_products_by_attributes',
    description: 'Search published products by one or more specification attribute values, e.g. a rated current or a color — something sulu_product_get_products cannot do since it only matches product titles and codes, not specs. Call sulu_product_get_attributes first to get the exact attribute keys.',
)]
final class SearchProductsByAttributesTool
{
    public function __construct(
        private readonly SearchProductsByAttributes $searchProductsByAttributes,
    ) {
    }

    /**
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param list<array{key: string, value: string}> $filters One to five {key, value} pairs,
     *                                                         ANDed together — a matching product must have every filter's attribute contain its
     *                                                         value. "key" is the exact attribute key from sulu_product_get_attributes, never its
     *                                                         translated name. "value" matches as a substring for a text or options attribute, or
     *                                                         an exact number for a number attribute when the value itself is numeric — never a
     *                                                         comparison, range, or wildcard like "16A or more" or "IP54 or higher", none of which
     *                                                         match anything and silently return no results. To find the best match among several
     *                                                         values, call this once per plausible literal value instead of describing a
     *                                                         threshold.
     * @param bool $includeVariants whether to include product variants (individual configurations
     *                              of a product with variants) in the results
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
