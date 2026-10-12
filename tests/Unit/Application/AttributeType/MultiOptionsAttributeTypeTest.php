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

namespace Sulu\Product\Tests\Unit\Application\AttributeType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\MultiOptionsAttributeType;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;

#[CoversClass(MultiOptionsAttributeType::class)]
class MultiOptionsAttributeTypeTest extends TestCase
{
    private function type(): MultiOptionsAttributeType
    {
        return new MultiOptionsAttributeType(new OptionsAttributeType());
    }

    public function testKey(): void
    {
        self::assertSame(AttributeInterface::TYPE_MULTI_OPTIONS, $this->type()->getKey());
    }

    public function testConfigureFieldAddsTheOptionsAsCollection(): void
    {
        $attribute = $this->attributeWithOptions();

        $field = new FieldMetadata('value');
        $field->setType('select');
        $this->type()->configureField($field, $attribute, 'en');

        $options = $field->getOptions();
        self::assertSame(['values'], \array_keys($options));
        self::assertSame(OptionMetadata::TYPE_COLLECTION, $options['values']->getType());
        $valueOptions = $options['values']->getValue();
        self::assertIsArray($valueOptions);
        self::assertCount(2, $valueOptions);
        self::assertSame('red', $valueOptions[0]->getName());
        self::assertSame('Red', $valueOptions[0]->getTitle('en'));
        self::assertSame('blue', $valueOptions[1]->getName());
        self::assertSame('blue', $valueOptions[1]->getTitle('en'));
    }

    public function testConfigureFieldYieldsTheSameOptionsAsTheOptionsType(): void
    {
        $attribute = $this->attributeWithOptions();

        $multiOptionsField = new FieldMetadata('value');
        $this->type()->configureField($multiOptionsField, $attribute, 'en');
        $optionsField = new FieldMetadata('value');
        (new OptionsAttributeType())->configureField($optionsField, $attribute, 'en');

        self::assertEquals($optionsField->getOptions(), $multiOptionsField->getOptions());
    }

    /**
     * An attribute with the options "red" (translated in en) and "blue" (untranslated).
     */
    private function attributeWithOptions(): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $red = new AttributeOption($attribute, 'red');
        $red->addTranslation(new AttributeOptionTranslation($red, 'en', 'Red'));
        $attribute->addOption($red);
        $attribute->addOption(new AttributeOption($attribute, 'blue'));

        return $attribute;
    }
}
