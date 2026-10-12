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

/**
 * An attribute type is its key and the form fragment `product_attribute_<key>`, whose fields are the
 * values of the attribute. The type configures each of these fields for an attribute, e.g. its bounds
 * or its choices.
 */
interface AttributeTypeInterface
{
    public function getKey(): string;

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void;
}
