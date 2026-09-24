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
use Sulu\Product\Application\AttributeType\AttributeValueView;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
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
     * @param iterable<AttributeValueView> $productAttributes
     * @param iterable<AttributeValueView> $variantAttributes
     *
     * @return array<string, AttributeValueView>
     */
    public function mergeAttributes(iterable $productAttributes, iterable $variantAttributes = []): array
    {
        $merged = [];

        foreach ([$productAttributes, $variantAttributes] as $attributes) {
            foreach ($attributes as $attributeValue) {
                $merged[$attributeValue->getKey()] = $attributeValue;
            }
        }

        return $merged;
    }

    /**
     * @param iterable<AttributeValueView> $attributeValues keys are ignored; re-keyed by attribute key
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

        foreach ($attributeValues as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            $formatted = $this->formatValue($attributeValue, $locale);

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
                'label' => $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey(),
                'type' => $attribute->getType(),
                'value' => $attributeValue->getValue(),
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
     * The value as displayed: option name, text, number or range with its display format, date in its display
     * format or the locale's default.
     * Without a locale the request's; null for an empty value or without any locale.
     */
    public function formatValue(AttributeValueView $attributeValue, ?string $locale = null): ?string
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return null;
        }

        $attribute = $attributeValue->getAttribute();
        $value = $attributeValue->getValue();

        return match ($attribute->getType()) {
            AttributeInterface::TYPE_OPTIONS => $this->formatOption($attribute, $value, $locale),
            AttributeInterface::TYPE_TEXT, AttributeInterface::TYPE_NUMBER => $this->applyDisplayFormat($attribute, $value),
            AttributeInterface::TYPE_DATE => $this->formatDate($attribute, $value, $locale),
            AttributeInterface::TYPE_RANGE => $this->formatRange($attribute, $value),
            default => null,
        };
    }

    private function formatOption(AttributeInterface $attribute, mixed $optionKey, string $locale): ?string
    {
        if (!\is_string($optionKey)) {
            return null;
        }

        return $attribute->getOption($optionKey)?->getTranslation($locale)?->getName() ?? $optionKey;
    }

    /**
     * A range renders as "from – to"; its display format may also place each bound on its own.
     */
    private function formatRange(AttributeInterface $attribute, mixed $range): ?string
    {
        $from = \is_array($range) ? $range['from'] ?? null : null;
        $to = \is_array($range) ? $range['to'] ?? null : null;

        if (!\is_float($from) || !\is_float($to)) {
            return null;
        }

        return $this->applyDisplayFormat($attribute, $from . ' – ' . $to, ['%from%' => (string) $from, '%to%' => (string) $to]);
    }

    /**
     * An editor can set a unit and a display format per attribute; without them the value renders bare.
     *
     * @param array<string, string> $placeholders further placeholders of the type, e.g. a range's bounds
     */
    private function applyDisplayFormat(AttributeInterface $attribute, mixed $value, array $placeholders = []): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value) || '' === $value) {
            return null;
        }

        $config = $attribute->getConfig();
        $format = $config['displayFormat'] ?? null;

        if (!\is_string($format) || '' === $format) {
            return (string) $value;
        }

        $unitKey = $config['unit'] ?? null;
        $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;

        $placeholders = ['%value%' => (string) $value, '%unit%' => $unit?->getSymbol() ?? ''] + $placeholders;

        return \trim(\str_replace(\array_keys($placeholders), \array_values($placeholders), $format));
    }

    /**
     * The display format is an ICU pattern, still rendered in the locale so month names translate;
     * without one the locale's medium date, because a bare `05.03.2024` names a different month elsewhere.
     * Rendered in UTC, because a date is stored as midnight UTC.
     */
    private function formatDate(AttributeInterface $attribute, mixed $date, string $locale): ?string
    {
        if (!\is_string($date)) {
            return null;
        }

        $format = $attribute->getConfig()['displayFormat'] ?? null;

        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            'UTC',
            null,
            \is_string($format) && '' !== $format ? $format : null,
        );

        return $formatter->format(new \DateTimeImmutable($date, new \DateTimeZone('UTC'))) ?: null;
    }
}
