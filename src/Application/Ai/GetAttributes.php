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

use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_attributes',
    description: 'List the known product specification attributes — their exact key, translated name, type, group and (for an "options" attribute) its possible option values — to build a precise sulu_product_get_attribute_values call instead of guessing an attribute key.',
)]
final class GetAttributes
{
    public function __construct(
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly MeasurementRegistry $measurementRegistry,
    ) {
    }

    /**
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param string|null $group attribute group name, or a substring of it, to list only that group's attributes
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     type: string,
     *     group: string,
     *     unit: ?string,
     *     options: list<array{key: string, label: string}>,
     * }>
     */
    public function __invoke(string $locale, ?string $group = null): array
    {
        $group = null !== $group ? \trim($group) : null;

        $attributes = $this->attributeRepository->findBy(selects: [
            AttributeRepositoryInterface::SELECT_ATTRIBUTE_TRANSLATIONS => true,
            AttributeRepositoryInterface::SELECT_ATTRIBUTE_GROUP => true,
            AttributeRepositoryInterface::SELECT_ATTRIBUTE_OPTIONS => true,
        ]);

        \usort(
            $attributes,
            static fn (AttributeInterface $a, AttributeInterface $b): int => [$a->getGroup()->getCreated(), $a->getGroup()->getUuid(), $a->getPosition()]
                <=> [$b->getGroup()->getCreated(), $b->getGroup()->getUuid(), $b->getPosition()],
        );

        $results = [];

        foreach ($attributes as $attribute) {
            $groupTranslation = $attribute->getGroup()->getTranslation($locale);
            $groupName = $groupTranslation?->getName() ?? $attribute->getGroup()->getUuid();

            if (null !== $group && '' !== $group && !\str_contains(\mb_strtolower($groupName), \mb_strtolower($group))) {
                continue;
            }

            $translation = $attribute->getTranslation($locale);

            $config = $attribute->getConfig();
            $unitKey = $config['unit'] ?? null;
            $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;

            $options = [];
            if (AttributeInterface::TYPE_OPTIONS === $attribute->getType()) {
                foreach ($attribute->getOptions() as $option) {
                    $options[] = [
                        'key' => $option->getKey(),
                        'label' => $option->getTranslation($locale)?->getName() ?? $option->getKey(),
                    ];
                }
            }

            $results[] = [
                'key' => $attribute->getKey(),
                'name' => $translation?->getName() ?? $attribute->getKey(),
                'type' => $attribute->getType(),
                'group' => $groupName,
                'unit' => $unit?->getSymbol(),
                'options' => $options,
            ];
        }

        return $results;
    }
}
