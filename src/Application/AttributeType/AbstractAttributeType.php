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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
    }

    /**
     * A single row, the default for a type whose value is one scalar.
     */
    public function getValueKeys(AttributeInterface $attribute, mixed $raw): array
    {
        return [ProductAttributeValueInterface::DEFAULT_VALUE_KEY];
    }
}
