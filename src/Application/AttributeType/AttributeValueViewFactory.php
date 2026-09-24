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

namespace Sulu\Product\Application\AttributeType;

use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

/**
 * Folds the stored rows of attribute values into one {@see AttributeValueView} per attribute.
 */
final class AttributeValueViewFactory
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @param iterable<ProductAttributeValueInterface> $rows
     *
     * @return array<string, AttributeValueView> keyed by attribute key
     */
    public function createMap(iterable $rows): array
    {
        /** @var array<string, array{attribute: AttributeInterface, rows: array<string, ProductAttributeValueInterface>}> $rowsByAttribute */
        $rowsByAttribute = [];
        foreach ($rows as $row) {
            $attribute = $row->getAttribute();
            $rowsByAttribute[$attribute->getKey()]['attribute'] = $attribute;
            $rowsByAttribute[$attribute->getKey()]['rows'][$row->getValueKey()] = $row;
        }

        $views = [];
        foreach ($rowsByAttribute as $key => ['attribute' => $attribute, 'rows' => $attributeRows]) {
            // A value whose type is no longer registered cannot be read; the page renders without it.
            if (!$this->attributeTypeRegistry->has($attribute->getType())) {
                continue;
            }

            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $views[$key] = new AttributeValueView($attribute, $type->readValue($attributeRows));
        }

        return $views;
    }
}
