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

/**
 * Converts the value of an attribute between the admin API and its rows.
 */
interface AttributeValueConverterInterface
{
    /**
     * The field types of the attribute type's form fragment by field name, in the fragment's order.
     *
     * @return array<string, string>
     */
    public function getFields(AttributeInterface $attribute): array;

    /**
     * The value as the admin API and the website see it: the field's value for a single field, an
     * object of them for several, null when none is set.
     *
     * @param iterable<ProductAttributeValueInterface> $rows the rows of the attribute, possibly none
     */
    public function read(AttributeInterface $attribute, iterable $rows): mixed;

    /**
     * The value of every attribute the rows belong to, by attribute uuid.
     *
     * @param iterable<ProductAttributeValueInterface> $rows
     *
     * @return array<string, array{attribute: AttributeInterface, value: mixed}>
     */
    public function readAll(iterable $rows): array;

    /**
     * The rows the value is stored in by value key, none for an empty value. Validates the whole
     * value, so a rejected value changes nothing.
     *
     * @return array<string, array{number?: float, text?: string, option?: AttributeOptionInterface}>
     */
    public function write(AttributeInterface $attribute, mixed $raw): array;
}
