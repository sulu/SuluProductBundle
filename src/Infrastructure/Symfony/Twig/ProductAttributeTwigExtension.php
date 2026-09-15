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
use Sulu\Product\Application\Attribute\ProductVariantAttributesMerger;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
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
        private readonly MeasurementRegistry $measurementRegistry,
        private readonly RequestAnalyzerInterface $requestAnalyzer,
        private readonly ProductVariantAttributesMerger $attributesMerger,
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
     * @param iterable<ProductAttributeValueInterface> $productAttributes
     * @param iterable<ProductAttributeValueInterface> $variantAttributes
     *
     * @return array<string, ProductAttributeValueInterface>
     */
    public function mergeAttributes(iterable $productAttributes, iterable $variantAttributes = []): array
    {
        return $this->attributesMerger->merge($productAttributes, $variantAttributes);
    }

    /**
     * @param iterable<ProductAttributeValueInterface> $productAttributes keys are ignored; re-keyed by attribute key
     *
     * @return list<array{key: string, label: string, attributes: array<string, ResolvedAttribute>}>
     */
    public function groupAttributes(iterable $productAttributes, ?string $locale = null): array
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return [];
        }

        /** @var array<string, array{key: string, label: string, attributes: array<string, ResolvedAttribute>}> $groups */
        $groups = [];

        foreach ($productAttributes as $productAttributeValue) {
            $attribute = $productAttributeValue->getAttribute();
            $formatted = $this->formatValue($productAttributeValue, $locale);

            if (null === $formatted || '' === $formatted) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupKey = (string) $group->getId();

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

        // group keys are database ids rendered as strings, so "10" must follow "9"
        \uksort($groups, static fn (int|string $a, int|string $b): int => (int) $a <=> (int) $b);

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
     * The value as displayed: option name, text or number with its display format, date per locale.
     * Without a locale the request's; null for an empty value or without any locale.
     */
    public function formatValue(ProductAttributeValueInterface $productAttributeValue, ?string $locale = null): ?string
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return null;
        }

        $attribute = $productAttributeValue->getAttribute();

        return match ($attribute->getType()) {
            AttributeInterface::TYPE_OPTIONS => $productAttributeValue->getAttributeOption()?->getTranslation($locale)?->getName()
                ?? $productAttributeValue->getAttributeOptionKey(),
            AttributeInterface::TYPE_TEXT => $this->applyDisplayFormat($attribute, $productAttributeValue->getText()),
            AttributeInterface::TYPE_NUMBER => $this->applyDisplayFormat($attribute, $productAttributeValue->getNumber()),
            AttributeInterface::TYPE_DATE => $this->formatDate($productAttributeValue->getNumber(), $locale),
            default => null,
        };
    }

    /** An editor can set a unit and a display format per attribute; without them the value renders bare. */
    private function applyDisplayFormat(AttributeInterface $attribute, string|float|null $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $config = $attribute->getConfig();
        $format = $config['displayFormat'] ?? null;

        if (!\is_string($format) || '' === $format) {
            return (string) $value;
        }

        $unitKey = $config['unit'] ?? null;
        $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;

        return \trim(\str_replace(['%value%', '%unit%'], [(string) $value, $unit?->getSymbol() ?? ''], $format));
    }

    /** Formatted per locale, because a bare `05.03.2024` names a different month elsewhere. */
    private function formatDate(?float $timestamp, string $locale): ?string
    {
        if (null === $timestamp) {
            return null;
        }

        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $formatter->format(new \DateTimeImmutable('@' . (int) $timestamp)) ?: null;
    }
}
