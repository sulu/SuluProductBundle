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

/**
 * An attribute of a product as templates see it: the attribute and its value, read by its type
 * from however many rows the value is stored in. The value has the shape of the admin API: a
 * scalar for most types, `{from, to}` for a range.
 */
final class AttributeValueView
{
    public function __construct(
        private readonly AttributeInterface $attribute,
        private readonly mixed $value,
    ) {
    }

    public function getAttribute(): AttributeInterface
    {
        return $this->attribute;
    }

    public function getKey(): string
    {
        return $this->attribute->getKey();
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
