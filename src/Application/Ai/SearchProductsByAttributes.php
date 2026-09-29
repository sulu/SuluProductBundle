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
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Search published products by one or more specification attribute values. Framework-agnostic:
 * Infrastructure\Symfony\Ai\Tool\SearchProductsByAttributesTool wraps this for symfony/ai-agent.
 */
final class SearchProductsByAttributes
{
    use ResolvesLiveProductContentTrait;

    private const MAX_LIMIT = 25;

    private const MAX_FILTERS = 5;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly ProductUrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @param list<AttributeFilter> $filters
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
            $key = \trim($filter->key);
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

            $attributeValues[] = ['attribute' => $attribute, 'value' => $filter->value];
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
            $row = $this->toProductSummary($product, $locale, $this->urlGenerator);

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
