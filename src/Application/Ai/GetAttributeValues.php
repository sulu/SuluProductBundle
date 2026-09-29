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
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;

/**
 * List the actual values seen for one product attribute, most common first. Framework-agnostic:
 * Infrastructure\Symfony\Ai\Tool\GetAttributeValuesTool wraps this for symfony/ai-agent.
 */
final class GetAttributeValues
{
    use ResolvesLiveProductContentTrait;

    private const MAX_LIMIT = 30;

    public function __construct(
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly ProductAttributeValueRepositoryInterface $productAttributeValueRepository,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @return array{
     *     values: list<array{value: string, count: int}>,
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

        $rows = $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]);

        $type = $this->attributeTypeRegistry->get($attribute->getType());

        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($rows as $row) {
            $value = $this->displayAttributeValue($row, $type, $locale);

            if (null === $value) {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        \arsort($counts);

        $results = [];
        foreach ($counts as $value => $count) {
            // PHP casts a numeric string array key (e.g. "16") back to int; undo that here.
            $results[] = ['value' => (string) $value, 'count' => $count];

            if (\count($results) >= $limit) {
                break;
            }
        }

        return ['values' => $results, 'status' => 'ok', 'instruction' => null];
    }
}
