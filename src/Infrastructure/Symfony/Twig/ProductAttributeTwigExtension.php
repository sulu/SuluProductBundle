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

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Folds the flat attribute map into its attribute groups for display.
 *
 * @phpstan-type ResolvedAttribute array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}
 */
class ProductAttributeTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('sulu_product_attribute_groups', [$this, 'groupAttributes']),
        ];
    }

    /**
     * @param array<string, ResolvedAttribute> $attributes
     *
     * @return list<array{key: string, label: string, attributes: array<string, ResolvedAttribute>}>
     */
    public function groupAttributes(array $attributes): array
    {
        /** @var array<string, array{key: string, label: string, attributes: array<string, ResolvedAttribute>}> $groups */
        $groups = [];

        foreach ($attributes as $key => $attribute) {
            $group = $attribute['group'];

            // key and label both come from the same AttributeGroup entity, so "first label wins" cannot diverge
            $groups[$group['key']] ??= [
                'key' => $group['key'],
                'label' => $group['label'],
                'attributes' => [],
            ];

            $groups[$group['key']]['attributes'][$key] = $attribute;
        }

        // group keys are database ids rendered as strings, so "10" must follow "9"
        \uksort($groups, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

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
