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

namespace Sulu\Product\Application\Ai;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_products',
    description: 'Search published products by keyword, article code, or product family. It matches product titles and codes as text, nothing else. Never use this for a specification value (an attribute value such as a size, a material, a rating, ...). These never appear verbatim in a title or code and will always return no_match. Use sulu_product_get_attributes and sulu_product_get_attribute_values instead to find the right attribute and its exact value spelling.',
)]
final class GetProducts
{
    use ResolvesLiveProductContentTrait;

    private const MAX_LIMIT = 25;

    private const NO_MATCH_INSTRUCTION = 'No product title or code matched this search '
        . 'term. Do not name, guess, or construct any product code as a fallback. Tell the '
        . 'visitor plainly that no match was found.';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductUrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param string|null $query free-text search term, an article code or part of a product title, e.g. "ABC-123", leave empty and pass productFamily to list a whole family
     * @param string|null $productFamily product family name to narrow or list by, not known upfront, so search by query first and read the "productFamily" field of a result to learn one
     * @param bool $includeVariants whether to include product variants in the results, false for a family listing, true when searching an exact article code that may belong to a variant
     * @param int $limit maximum number of results to return, capped at 25
     *
     * @return array{
     *     results: list<array{code: string, title: string, productFamily: ?string, url: ?string}>,
     *     status: 'ok'|'no_match',
     *     instruction: ?string,
     * }
     */
    public function __invoke(
        string $locale,
        ?string $query = null,
        ?string $productFamily = null,
        bool $includeVariants = false,
        int $limit = 10,
    ): array {
        $query = null !== $query ? \trim($query) : null;
        $productFamily = null !== $productFamily ? \trim($productFamily) : null;

        if (('' === $query || null === $query) && ('' === $productFamily || null === $productFamily)) {
            return ['results' => [], 'status' => 'no_match', 'instruction' => self::NO_MATCH_INSTRUCTION];
        }

        $filters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'limit' => \max(1, \min($limit, self::MAX_LIMIT)),
        ];

        if ('' !== $query && null !== $query) {
            $filters['query'] = $query;
        }

        if ('' !== $productFamily && null !== $productFamily) {
            $filters['productFamilyName'] = $productFamily;
        }

        if (!$includeVariants) {
            $filters['excludeTypes'] = [ProductInterface::TYPE_VARIANT];
        }

        $results = [];

        foreach ($this->productRepository->findBy($filters, ['title' => 'asc']) as $product) {
            $row = $this->toProductSummary($product, $locale, $this->urlGenerator);

            if (null !== $row) {
                $results[] = $row;
            }
        }

        return [
            'results' => $results,
            'status' => [] === $results ? 'no_match' : 'ok',
            'instruction' => [] === $results ? self::NO_MATCH_INSTRUCTION : null,
        ];
    }
}
