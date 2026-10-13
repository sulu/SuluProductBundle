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

use Sulu\Product\Application\Attribute\AttributeValueConverterInterface;
use Sulu\Product\Domain\Model\AttributeInterface;

/**
 * An attribute type that renders its own display text. A type without it is shown as plain text.
 */
interface FormattableAttributeTypeInterface
{
    /**
     * @param mixed $value as {@see AttributeValueConverterInterface::normalize()} returns it
     *
     * @return string|bool|null a bool for a type a template tests, null for an empty value
     */
    public function formatValue(AttributeInterface $attribute, mixed $value, string $locale): string|bool|null;

    /**
     * Whether an editor can set a display format for attributes of this type.
     */
    public function hasDisplayFormat(): bool;
}
