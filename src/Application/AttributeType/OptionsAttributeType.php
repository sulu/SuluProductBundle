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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;

final class OptionsAttributeType extends AbstractAttributeType
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_OPTIONS;
    }

    protected function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
        $values = new OptionMetadata();
        $values->setName('values');
        $values->setType(OptionMetadata::TYPE_COLLECTION);

        foreach ($attribute->getOptions() as $option) {
            $valueOption = new OptionMetadata();
            $valueOption->setName($option->getKey());
            $valueOption->setValue($option->getKey());
            $valueOption->setTitle($option->getTranslation($locale)?->getName() ?? $option->getKey(), $locale);
            $values->addValueOption($valueOption);
        }

        $field->addOption($values);
    }
}
