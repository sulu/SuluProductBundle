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
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Combines a product's and a variant's attribute values, formats them and folds them into their
 * attribute groups for display.
 *
 * @phpstan-type ResolvedAttribute array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}
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
            new TwigFilter('sulu_product_format_attribute_value', [$this, 'formatValue']),
        ];
    }

    /**
     * Combines a product's attribute values with those of one of its variants, keyed by attribute
     * key; a variant's value wins over the product's.
     *
     * @param iterable<ProductAttributeValueInterface> $productAttributes
     * @param iterable<ProductAttributeValueInterface> $variantAttributes
     *
     * @return array<string, ProductAttributeValueInterface>
     */
    public function mergeAttributes(iterable $productAttributes, iterable $variantAttributes = []): array
    {
        $merged = [];

        foreach ([$productAttributes, $variantAttributes] as $attributes) {
            foreach ($attributes as $value) {
                $merged[$value->getAttribute()->getKey()] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param iterable<ProductAttributeValueInterface> $productAttributeValues keys are ignored; re-keyed by attribute key
     *
     * @return list<array{key: string, label: string, attributes: array<string, ResolvedAttribute>}>
     */
    public function groupAttributes(iterable $productAttributeValues, ?string $locale = null): array
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return [];
        }

        /** @var array<string, array{key: string, label: string, attributes: array<string, ResolvedAttribute>}> $groups */
        $groups = [];

        foreach ($productAttributeValues as $productAttributeValue) {
            $attribute = $productAttributeValue->getAttribute();
            $formatted = $this->formatValue($productAttributeValue, $locale);

            if (null === $formatted || '' === $formatted) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupKey = $group->getUuid();

            $groups[$groupKey] ??= [
                'key' => $groupKey,
                'label' => $group->getTranslation($locale)?->getName() ?? $groupKey,
                'attributes' => [],
            ];

            $groups[$groupKey]['attributes'][$attribute->getKey()] = [
                'key' => $attribute->getKey(),
                'label' => $attribute->getTranslation($locale)?->getName() ?? $productAttributeValue->getAttributeKey(),
                'type' => $attribute->getType(),
                'value' => $productAttributeValue->getValue(),
                'formattedValue' => $formatted,
                'position' => $attribute->getPosition(),
                'group' => [
                    'key' => $groupKey,
                    'label' => $groups[$groupKey]['label'],
                ],
            ];
        }

        // uuid v7 strings sort by creation time
        \uksort($groups, static fn (string $a, string $b): int => \strcmp($a, $b));

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

    /**
     * The value as displayed, see ProductAttributeValueFormatter. Without a locale the request's;
     * null for an empty value or without any locale.
     */
    public function formatValue(ProductAttributeValueInterface $productAttributeValue, ?string $locale = null): ?string
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return null;
        }

        return $this->formatter->format($productAttributeValue, $locale);
    }
}
