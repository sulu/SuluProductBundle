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

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_search_products_by_attributes',
    description: 'Search published products by one or more specification attribute values, e.g. a rated current or a color — something sulu_product_get_products cannot do since it only matches product titles and codes, not specs. Call sulu_product_get_attributes first to get the exact attribute keys.',
)]
final class SearchProductsByAttributesTool
{
    use ResolvesLiveProductContentTrait;

    private const MAX_LIMIT = 25;

    private const MAX_FILTERS = 5;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AttributeRepositoryInterface $attributeRepository,
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
        if ([] === $filters) {
            return [
                'results' => [],
                'status' => 'no_match',
                'instruction' => 'No filters were given. Call sulu_product_get_attributes first '
                    . 'and pass at least one {key, value} pair.',
            ];
        }

        $attributeValues = [];

        foreach (\array_slice($filters, 0, self::MAX_FILTERS) as $filter) {
            $key = \trim($filter['key']);
            $attribute = '' !== $key ? $this->attributeRepository->findOneBy(['key' => $key]) : null;

            if (null === $attribute) {
                return [
                    'results' => [],
                    'status' => 'unknown_attribute',
                    'instruction' => \sprintf(
                        'No attribute with the exact key "%s" exists. Call sulu_product_get_attributes to get the exact key instead of guessing one.',
                        $key,
                    ),
                ];
            }

            $attributeValues[] = ['attribute' => $attribute, 'value' => $filter['value']];
        }

        $productFilters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'attributeValues' => $attributeValues,
            'limit' => \max(1, \min($limit, self::MAX_LIMIT)),
        ];

        if (!$includeVariants) {
            $productFilters['excludeTypes'] = [ProductInterface::TYPE_VARIANT];
        }

        $results = [];

        foreach ($this->productRepository->findBy($productFilters, ['title' => 'asc']) as $product) {
            $row = $this->toProductSummary($product, $locale);

            if (null !== $row) {
                $results[] = $row;
            }
        }

        return [
            'results' => $results,
            'status' => [] === $results ? 'no_match' : 'ok',
            'instruction' => [] === $results ? 'No product matched every given attribute value. '
                . 'Do not name, guess, or construct any product code as a fallback. Tell the '
                . 'visitor plainly that no match was found.' : null,
        ];
    }
}
