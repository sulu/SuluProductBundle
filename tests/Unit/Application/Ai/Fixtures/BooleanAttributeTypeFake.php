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

namespace Sulu\Product\Tests\Unit\Application\Ai\Fixtures;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

/**
 * Stands in for an AttributeTypeInterface a bundle consumer might register whose readValue()
 * does not return the string|float|null this bundle's own built-in types return.
 */
final class BooleanAttributeTypeFake implements AttributeTypeInterface
{
    public function getKey(): string
    {
        return 'custom_boolean';
    }

    public function getFormKey(): string
    {
        return 'custom_boolean';
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
    }

    public function readValue(ProductAttributeValueInterface $value): mixed
    {
        return true;
    }

    public function writeValue(ProductAttributeValueInterface $value, mixed $raw): void
    {
    }
}
