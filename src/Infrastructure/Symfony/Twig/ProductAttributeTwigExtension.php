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

namespace Sulu\Product\Infrastructure\Symfony\Twig;

use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Model\AttributeInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Combines a product's and a variant's attribute values, formats them and folds them into their
 * attribute groups for display.
 *
 * @phpstan-type AttributeValue array{attribute: AttributeInterface, value: mixed, formattedValue?: string|bool|null}
 * @phpstan-type ResolvedAttribute array{key: string, label: string, type: string, value: mixed, formattedValue: string|bool, position: int, group: array{key: string, label: string}}
 */
class ProductAttributeTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductAttributeValueFormatter $formatter,
        private readonly RequestAnalyzerInterface $requestAnalyzer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sulu_product_merge_attributes', [$this, 'mergeAttributes']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sulu_product_attribute_groups', [$this, 'groupAttributes']),
        ];
    }

    /**
     * Combines a product's attribute values with those of one of its variants, keyed by attribute
     * key; a variant's value wins over the product's.
     *
     * @param iterable<AttributeValue> $productAttributes
     * @param iterable<AttributeValue> $variantAttributes
     *
     * @return array<string, AttributeValue>
     */
    public function mergeAttributes(iterable $productAttributes, iterable $variantAttributes = []): array
    {
        $merged = [];

        foreach ([$productAttributes, $variantAttributes] as $attributes) {
            foreach ($attributes as $attributeValue) {
                $merged[$attributeValue['attribute']->getKey()] = $attributeValue;
            }
        }

        return $merged;
    }

    /**
     * @param iterable<AttributeValue> $attributeValues keys are ignored; re-keyed by attribute key
     *
     * @return list<array{key: string, label: string, attributes: array<string, ResolvedAttribute>}>
     */
    public function groupAttributes(iterable $attributeValues, ?string $locale = null): array
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return [];
        }

        /** @var array<string, array{key: string, label: string, attributes: array<string, ResolvedAttribute>}> $groups */
        $groups = [];
        /** @var array<string, array{\DateTimeImmutable, string}> $groupOrder */
        $groupOrder = [];

        foreach ($attributeValues as $attributeValue) {
            $attribute = $attributeValue['attribute'];
            // The resolver has formatted its values already; formatting an option again costs a query.
            $formatted = \array_key_exists('formattedValue', $attributeValue)
                ? $attributeValue['formattedValue']
                : $this->formatter->format($attribute, $attributeValue['value'], $locale);

            if (null === $formatted || '' === $formatted) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupKey = $group->getUuid();
            $groupOrder[$groupKey] ??= [$group->getCreated(), $groupKey];

            $groups[$groupKey] ??= [
                'key' => $groupKey,
                'label' => $group->getTranslation($locale)?->getName() ?? $groupKey,
                'attributes' => [],
            ];

            $groups[$groupKey]['attributes'][$attribute->getKey()] = [
                'key' => $attribute->getKey(),
                'label' => $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey(),
                'type' => $attribute->getType(),
                'value' => $attributeValue['value'],
                'formattedValue' => $formatted,
                'position' => $attribute->getPosition(),
                'group' => [
                    'key' => $groupKey,
                    'label' => $groups[$groupKey]['label'],
                ],
            ];
        }

        \uksort($groups, static fn (string $a, string $b): int => $groupOrder[$a] <=> $groupOrder[$b]);

        $result = [];
        foreach ($groups as $group) {
            \uasort(
                $group['attributes'],
                static fn (array $a, array $b): int => $a['position'] <=> $b['position'],
            );

            $result[] = $group;
        }

        return $result;
    }
}
