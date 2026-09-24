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

    public function readValue(array $rows): mixed
    {
        return ($rows[ProductAttributeValueInterface::DEFAULT_VALUE_KEY] ?? null)?->getAttributeOptionKey();
    }

    public function writeValue(array $rows, mixed $raw): void
    {
        $value = $rows[ProductAttributeValueInterface::DEFAULT_VALUE_KEY];

        if (null === $raw || '' === $raw) {
            $value->setAttributeOption(null);

            return;
        }

        Assert::string($raw);

        $option = $value->getAttribute()->getOption($raw);
        Assert::notNull($option, \sprintf('Attribute "%s" has no option "%s".', $value->getAttributeKey(), $raw));

        $value->setAttributeOption($option);
    }
}
