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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
        foreach ($form->getItems() as $item) {
            if ($item instanceof FieldMetadata) {
                $this->configureField($item, $attribute, $locale);
            }
        }
    }

    /**
     * Called for each field of the form; override it when every field is configured alike.
     */
    protected function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
    }
}
