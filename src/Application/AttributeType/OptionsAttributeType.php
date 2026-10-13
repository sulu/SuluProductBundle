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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;

final class OptionsAttributeType implements AttributeTypeInterface, FormattableAttributeTypeInterface
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_OPTIONS;
    }

    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
        foreach ($form->getItems() as $field) {
            if (!$field instanceof FieldMetadata) {
                continue;
            }

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

    /**
     * The option's name in the locale, its key without one.
     */
    public function formatValue(AttributeInterface $attribute, mixed $value, string $locale): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        return $attribute->getOption($value)?->getTranslation($locale)?->getName() ?? $value;
    }

    public function hasDisplayFormat(): bool
    {
        return false;
    }
}
