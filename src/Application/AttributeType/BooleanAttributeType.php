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
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * Yes, no or no value. Read as "true"/"false", because the admin's single_select only takes string
 * values; written from those or a real bool. Stored as 1 or 0 in the number column.
 */
final class BooleanAttributeType extends AbstractAttributeType
{
    public const TRUE = 'true';
    public const FALSE = 'false';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_BOOLEAN;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_boolean';
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
        $values = new OptionMetadata();
        $values->setName('values');
        $values->setType(OptionMetadata::TYPE_COLLECTION);

        $choices = [self::TRUE => 'sulu_admin.yes', self::FALSE => 'sulu_admin.no'];
        if (!$field->isRequired()) {
            // An empty name is the admin's "no value" option, so an optional attribute can be cleared.
            $choices = ['' => 'sulu_admin.please_choose'] + $choices;
        }

        foreach ($choices as $value => $title) {
            $value = (string) $value;
            $valueOption = new OptionMetadata();
            $valueOption->setName($value);
            $valueOption->setValue('' !== $value ? $value : null);
            $valueOption->setTitle($this->translator->trans($title, [], 'admin', $locale), $locale);
            $values->addValueOption($valueOption);
        }

        $field->addOption($values);
    }

    public function readValue(ProductAttributeValueInterface $value): mixed
    {
        $number = $value->getNumber();

        if (null === $number) {
            return null;
        }

        return 0.0 !== $number ? self::TRUE : self::FALSE;
    }

    public function writeValue(ProductAttributeValueInterface $value, mixed $raw): void
    {
        if (null === $raw || '' === $raw) {
            $value->setNumber(null);

            return;
        }

        if (\is_string($raw)) {
            Assert::oneOf($raw, [self::TRUE, self::FALSE]);
            $raw = self::TRUE === $raw;
        }

        Assert::boolean($raw);

        $value->setNumber($raw ? 1.0 : 0.0);
    }
}
