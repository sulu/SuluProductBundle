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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeTranslation;

#[CoversClass(Attribute::class)]
class AttributeTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertNull($attribute->getUuid());
        $this->assertSame(AttributeInterface::TYPE_NUMBER, $attribute->getType());
        $this->assertSame([], $attribute->getOptions());
        $this->assertNull($attribute->getTranslation('en'));
    }

    public function testSetUuidIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setUuid('uuid-value'));
        $this->assertSame('uuid-value', $attribute->getUuid());
    }

    public function testSetExternalIdentifierIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setExternalIdentifier('ext-123'));
        $this->assertSame('ext-123', $attribute->getExternalIdentifier());
        $attribute->setExternalIdentifier(null);
        $this->assertNull($attribute->getExternalIdentifier());
    }

    public function testSetKeyIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setKey('color'));
        $this->assertSame('color', $attribute->getKey());
    }

    public function testSetTypeIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setType(AttributeInterface::TYPE_TEXT));
        $this->assertSame(AttributeInterface::TYPE_TEXT, $attribute->getType());
    }

    public function testMeasurementFamilyDefaultsToNull(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertNull($attribute->getMeasurementFamily());
    }

    public function testSetMeasurementFamilyIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setMeasurementFamily('weight'));
        $this->assertSame('weight', $attribute->getMeasurementFamily());
        $attribute->setMeasurementFamily(null);
        $this->assertNull($attribute->getMeasurementFamily());
    }

    public function testUnitDefaultsToNull(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertNull($attribute->getUnit());
    }

    public function testSetUnitIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setUnit('KILOGRAM'));
        $this->assertSame('KILOGRAM', $attribute->getUnit());
        $attribute->setUnit(null);
        $this->assertNull($attribute->getUnit());
    }

    public function testGetTranslationByExplicitLocale(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $en = new AttributeTranslation($attribute, 'en', 'Color');
        $de = new AttributeTranslation($attribute, 'de', 'Farbe');
        $attribute->addTranslation($en);
        $attribute->addTranslation($de);

        $this->assertSame($en, $attribute->getTranslation('en'));
        $this->assertSame($de, $attribute->getTranslation('de'));
        $this->assertNull($attribute->getTranslation('fr'));
    }

    public function testAddTranslationIsFluentAndDeduplicates(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $translation = new AttributeTranslation($attribute, 'en', 'Color');

        $this->assertSame($attribute, $attribute->addTranslation($translation));
        $attribute->addTranslation($translation);

        // Adding same instance twice should not duplicate; only one translation lookup
        $this->assertSame($translation, $attribute->getTranslation('en'));
    }

    public function testRemoveTranslationIsFluent(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $translation = new AttributeTranslation($attribute, 'en', 'Color');
        $attribute->addTranslation($translation);

        $this->assertSame($attribute, $attribute->removeTranslation($translation));
        $this->assertNull($attribute->getTranslation('en'));
    }

    public function testGetOptionsReturnsArray(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $option = new AttributeOption($attribute, 'red');

        $attribute->addOption($option);

        $this->assertSame([$option], $attribute->getOptions());
    }

    public function testGetOptionByKey(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $red = new AttributeOption($attribute, 'red');
        $blue = new AttributeOption($attribute, 'blue');
        $attribute->addOption($red);
        $attribute->addOption($blue);

        $this->assertSame($red, $attribute->getOption('red'));
        $this->assertSame($blue, $attribute->getOption('blue'));
        $this->assertNull($attribute->getOption('green'));
    }

    public function testAddOptionIsFluentAndDeduplicates(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $option = new AttributeOption($attribute, 'red');

        $this->assertSame($attribute, $attribute->addOption($option));
        $attribute->addOption($option);

        $this->assertCount(1, $attribute->getOptions());
    }

    public function testRemoveOptionIsFluent(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $option = new AttributeOption($attribute, 'red');
        $attribute->addOption($option);

        $this->assertSame($attribute, $attribute->removeOption($option));
        $this->assertCount(0, $attribute->getOptions());
    }

    public function testSetGroupIsFluentAndStores(): void
    {
        $originalGroup = new AttributeGroup();
        $newGroup = new AttributeGroup();
        $attribute = new Attribute($originalGroup);

        $this->assertSame($attribute, $attribute->setGroup($newGroup));
        $this->assertSame($newGroup, $attribute->getGroup());
    }

    public function testMinDefaultsToNull(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertNull($attribute->getMin());
    }

    public function testSetMinIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setMin(1.5));
        $this->assertSame(1.5, $attribute->getMin());
    }

    public function testSetMinNullClearsValue(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setMin(5.0);
        $attribute->setMin(null);
        $this->assertNull($attribute->getMin());
    }

    public function testMaxDefaultsToNull(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertNull($attribute->getMax());
    }

    public function testSetMaxIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $this->assertSame($attribute, $attribute->setMax(99.9));
        $this->assertSame(99.9, $attribute->getMax());
    }

    public function testSetMaxNullClearsValue(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setMax(10.0);
        $attribute->setMax(null);
        $this->assertNull($attribute->getMax());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new Attribute(new AttributeGroup());
        $ref = new \ReflectionProperty(Attribute::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
