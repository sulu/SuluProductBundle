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
 * A type whose value is stored in one row: it reads and writes that row and never deals with
 * value keys.
 */
abstract class AbstractSingleRowAttributeType extends AbstractAttributeType
{
    final public function getValueKeys(AttributeInterface $attribute, mixed $raw): array
    {
        return [ProductAttributeValueInterface::DEFAULT_VALUE_KEY];
    }

    final public function readValue(array $rows): mixed
    {
        $row = $rows[ProductAttributeValueInterface::DEFAULT_VALUE_KEY] ?? null;

        return null === $row ? null : $this->readRow($row);
    }

    final public function writeValue(array $rows, mixed $raw): void
    {
        $this->writeRow($rows[ProductAttributeValueInterface::DEFAULT_VALUE_KEY], $raw);
    }

    abstract protected function readRow(ProductAttributeValueInterface $row): mixed;

    /**
     * Validates the value before changing the row.
     */
    abstract protected function writeRow(ProductAttributeValueInterface $row, mixed $raw): void;
}
