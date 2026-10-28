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

namespace Sulu\Product\Application\Attribute;

use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

interface AttributeValueConverterInterface
{
    /**
     * The field types of the attribute type's form fragment by field name, in the fragment's order.
     *
     * @return array<string, string>
     */
    public function getFields(AttributeInterface $attribute): array;

    /**
     * The field's value for a single field, an object of them for several, null when none is set.
     *
     * @param iterable<ProductAttributeValueInterface> $rows the rows of the attribute, possibly none
     */
    public function normalize(AttributeInterface $attribute, iterable $rows): mixed;

    /**
     * @param iterable<ProductAttributeValueInterface> $rows
     *
     * @return array<string, array{attribute: AttributeInterface, value: mixed}> by attribute uuid
     */
    public function normalizeAll(iterable $rows): array;

    /**
     * Validates the whole value, so a rejected value changes nothing.
     *
     * @return array<string, array{number?: float, text?: string, option?: AttributeOptionInterface}> by value key, none for an empty value
     */
    public function denormalize(AttributeInterface $attribute, mixed $raw): array;
}
