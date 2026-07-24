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

interface AttributeTypeInterface
{
    public function getKey(): string;

    public function getFormKey(): string;

    public function isAvailableInAdmin(): bool;

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void;

    public function readValue(ProductAttributeValueInterface $value): mixed;

    public function writeValue(ProductAttributeValueInterface $value, mixed $raw): void;
}
