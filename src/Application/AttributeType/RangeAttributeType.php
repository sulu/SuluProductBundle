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
 * Two numbers, "from" and "to", either of which may be empty for a range open on one side. The
 * attribute's min, max and step apply to both.
 */
final class RangeAttributeType extends AbstractAttributeType
{
    public function __construct(
        private readonly NumberAttributeType $numberAttributeType,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_RANGE;
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
        $this->numberAttributeType->configureField($field, $attribute, $locale);
    }
}
