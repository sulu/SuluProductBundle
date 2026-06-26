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
use Webmozart\Assert\Assert;

final class OptionsAttributeType extends AbstractAttributeType
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_OPTIONS;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_options';
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
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

    public function readValue(ProductAttributeValueInterface $value): mixed
    {
        return $value->getAttributeOptionKey();
    }

    public function writeValue(ProductAttributeValueInterface $value, mixed $raw): void
    {
        if (null === $raw || '' === $raw) {
            $value->setAttributeOptionKey(null);

            return;
        }

        Assert::string($raw);

        $value->setAttributeOptionKey($raw);
    }
}
