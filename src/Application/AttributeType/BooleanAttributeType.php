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
use Sulu\Product\Application\Attribute\AttributeValueConverter;
use Sulu\Product\Domain\Model\AttributeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Yes, no or no value, chosen in a single_select with the values "true" and "false".
 */
final class BooleanAttributeType implements AttributeTypeInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_BOOLEAN;
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
        $values = new OptionMetadata();
        $values->setName('values');
        $values->setType(OptionMetadata::TYPE_COLLECTION);

        $choices = [AttributeValueConverter::TRUE => 'sulu_admin.yes', AttributeValueConverter::FALSE => 'sulu_admin.no'];
        if (!$field->isRequired()) {
            // An empty name is the admin's "no value" option, so an optional attribute can be cleared.
            $choices = ['' => 'sulu_product.not_specified'] + $choices;
        }

        foreach ($choices as $value => $title) {
            $valueOption = new OptionMetadata();
            $valueOption->setName($value);
            $valueOption->setValue('' !== $value ? $value : null);
            $valueOption->setTitle($this->translator->trans($title, [], 'admin', $locale), $locale);
            $values->addValueOption($valueOption);
        }

        $field->addOption($values);
    }
}
