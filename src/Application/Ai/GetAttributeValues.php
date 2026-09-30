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
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_attribute_values',
    description: 'List the actual values seen for one product attribute, most common first — call this before searching by a specification value whenever its exact spelling is unclear, instead of guessing and getting zero results because the data is spelled differently. Call sulu_product_get_attributes first to get the exact attribute key. Each value has a display text and a searchValue: pass the searchValue, not the display text, as a filter value to sulu_product_search_products_by_attributes.',
)]
final class GetAttributeValues
{
    private const MAX_LIMIT = 30;

    public function __construct(
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly ProductAttributeValueRepositoryInterface $productAttributeValueRepository,
        private readonly ProductAttributeValueFormatter $valueFormatter,
    ) {
    }

    /**
     * @param string $key exact attribute key from sulu_product_get_attributes, not its translated name
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param int $limit maximum number of distinct values to return, capped at 30
     *
     * @return array{
     *     values: list<array{value: string, searchValue: string, count: int}>,
     *     status: 'ok'|'unknown_attribute',
     *     instruction: ?string,
     * }
     */
    public function __invoke(string $key, string $locale, int $limit = 15): array
    {
        $key = \trim($key);
        $attribute = '' !== $key ? $this->attributeRepository->findOneBy(['key' => $key]) : null;

        if (null === $attribute) {
            return [
                'values' => [],
                'status' => 'unknown_attribute',
                'instruction' => 'No attribute with this exact key exists. Call '
                    . 'sulu_product_get_attributes to get the exact key instead of guessing one.',
            ];
        }

        $limit = \max(1, \min($limit, self::MAX_LIMIT));

        $groups = $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ], $limit);

        /** @var array<string, int> $counts */
        $counts = [];
        /** @var array<string, string> $searchValues */
        $searchValues = [];
        foreach ($groups as $group) {
            $value = $this->valueFormatter->format($group['value'], $locale);

            if (null === $value || '' === \trim($value)) {
                continue;
            }

            $value = \trim($value);
            $counts[$value] = ($counts[$value] ?? 0) + $group['count'];
            $searchValues[$value] ??= $this->searchValue($group['value']);
        }

        \arsort($counts);

        $results = [];
        foreach ($counts as $value => $count) {
            // PHP casts a numeric string array key (e.g. "16") back to int; undo that here.
            $value = (string) $value;
            $results[] = ['value' => $value, 'searchValue' => $searchValues[$value], 'count' => $count];

            if (\count($results) >= $limit) {
                break;
            }
        }

        return ['values' => $results, 'status' => 'ok', 'instruction' => null];
    }

    /**
     * What the attribute search matches: the option key, the text or the bare number, never the display text.
     */
    private function searchValue(ProductAttributeValueInterface $value): string
    {
        $number = $value->getNumber();

        return $value->getAttributeOptionKey()
            ?? $value->getText()
            ?? (string) $number;
    }
}
