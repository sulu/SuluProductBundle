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
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Formats the resolved attribute values and folds them into their attribute groups for display.
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

    public function getFilters(): array
    {
        return [
            new TwigFilter('sulu_product_attribute_groups', [$this, 'groupAttributes']),
        ];
    }

    /**
     * @param iterable<ProductAttributeValueInterface> $attributes keys are ignored; re-keyed by attribute key
     *
     * @return list<array{key: string, label: string, attributes: array<string, ResolvedAttribute>}>
     */
    public function groupAttributes(iterable $attributes, ?string $locale = null): array
    {
        $locale ??= $this->requestAnalyzer->getCurrentLocalization()?->getLocale();

        if (null === $locale) {
            return [];
        }

        /** @var array<string, array{key: string, label: string, attributes: array<string, ResolvedAttribute>}> $groups */
        $groups = [];

        foreach ($attributes as $value) {
            $attribute = $value->getAttribute();
            $formatted = $this->formatValue($value, $attribute, $locale);

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
                'label' => $attribute->getTranslation($locale)?->getName() ?? $value->getAttributeKey(),
                'type' => $attribute->getType(),
                'value' => $value->getValue(),
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

    private function formatValue(
        ProductAttributeValueInterface $value,
        AttributeInterface $attribute,
        string $locale,
    ): ?string {
        return match ($attribute->getType()) {
            AttributeInterface::TYPE_OPTIONS => $value->getAttributeOption()?->getTranslation($locale)?->getName()
                ?? $value->getAttributeOptionKey(),
            AttributeInterface::TYPE_TEXT => $this->applyDisplayFormat($attribute, $value->getText()),
            AttributeInterface::TYPE_NUMBER => $this->applyDisplayFormat($attribute, $value->getNumber()),
            AttributeInterface::TYPE_DATE => $this->formatDate($value->getNumber(), $locale),
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
