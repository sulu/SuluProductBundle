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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeFieldFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(AttributeFieldFactory::class)]
class AttributeFieldFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FormMetadataLoaderInterface> */
    private ObjectProphecy $formMetadataLoader;

    protected function setUp(): void
    {
        $this->formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
    }

    private function factory(): AttributeFieldFactory
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Unit');

        return new AttributeFieldFactory(
            new AttributeTypeRegistry([new NumberAttributeType()]),
            $this->formMetadataLoader->reveal(),
            new MeasurementRegistry(),
            $translator,
        );
    }

    /**
     * A 'value' field carrying an option and a block type, so the option/block-type
     * cloning loops in {@see AttributeFieldFactory::cloneFieldWithName()} run at least once.
     */
    private function fragmentWithValueField(): FormMetadata
    {
        $field = new FieldMetadata('value');
        $field->setType('number');
        $field->setColSpan(12);

        $option = new OptionMetadata();
        $option->setName('step');
        $option->setValue('1');
        $field->addOption($option);

        $blockType = new FormMetadata();
        $blockType->setKey('block_type_1');
        $field->addType($blockType);

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $fragment->addItem($field);

        return $fragment;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return ObjectProphecy<AttributeInterface>
     */
    private function attribute(
        int $id,
        string $key,
        string $type,
        array $config,
        ?string $localeTranslationName,
        ?string $description = null,
        ?string $defaultLocale = null,
        ?string $defaultLocaleTranslationName = null,
    ): ObjectProphecy {
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn($id);
        $attribute->getKey()->willReturn($key);
        $attribute->getType()->willReturn($type);
        $attribute->getConfig()->willReturn($config);
        $attribute->getDefaultLocale()->willReturn($defaultLocale);

        if (null !== $localeTranslationName) {
            $translation = $this->prophesize(AttributeTranslationInterface::class);
            $translation->getName()->willReturn($localeTranslationName);
            $translation->getDescription()->willReturn($description);
            $attribute->getTranslation('en')->willReturn($translation->reveal());
        } else {
            $attribute->getTranslation('en')->willReturn(null);
        }

        if (null !== $defaultLocale) {
            if (null !== $defaultLocaleTranslationName) {
                $defaultTranslation = $this->prophesize(AttributeTranslationInterface::class);
                $defaultTranslation->getName()->willReturn($defaultLocaleTranslationName);
                $defaultTranslation->getDescription()->willReturn(null);
                $attribute->getTranslation($defaultLocale)->willReturn($defaultTranslation->reveal());
            } else {
                $attribute->getTranslation($defaultLocale)->willReturn(null);
            }
        }

        return $attribute;
    }

    /**
     * @return ObjectProphecy<ProductFamilyAttributeInterface>
     */
    private function familyAttribute(AttributeInterface $attribute, bool $required = false): ObjectProphecy
    {
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute);
        $familyAttribute->isRequired()->willReturn($required);

        return $familyAttribute;
    }

    public function testReturnsNullWhenAttributeTypeIsUnknown(): void
    {
        $attribute = $this->attribute(1, 'color', AttributeInterface::TYPE_TEXT, [], 'Color');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        self::assertNull($this->factory()->build($familyAttribute->reveal(), 'en'));
    }

    public function testReturnsNullWhenTemplateIsNotFormMetadata(): void
    {
        $attribute = $this->attribute(1, 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn(null);

        self::assertNull($this->factory()->build($familyAttribute->reveal(), 'en'));
    }

    public function testReturnsNullWhenTemplateHasNoValueField(): void
    {
        $attribute = $this->attribute(1, 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn($fragment);

        self::assertNull($this->factory()->build($familyAttribute->reveal(), 'en'));
    }

    public function testBuildsFieldWithTranslationForRequestedLocaleAndNoUnit(): void
    {
        $attribute = $this->attribute(7, 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight', '<b>Heavy</b> item');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $result = $this->factory()->build($familyAttribute->reveal(), 'en');

        self::assertNotNull($result);
        [$field, $unitField] = $result;

        self::assertSame('attributes/7', $field->getName());
        self::assertSame('Weight', $field->getLabel('en'));
        self::assertTrue($field->isRequired());
        self::assertSame('Heavy item', $field->getDescription('en'));
        self::assertSame(12, $field->getColSpan());
        self::assertNull($unitField);
    }

    public function testFallsBackToDefaultLocaleTranslationWhenRequestedLocaleHasNone(): void
    {
        $attribute = $this->attribute(
            2,
            'weight',
            AttributeInterface::TYPE_NUMBER,
            [],
            null,
            null,
            'de',
            'Gewicht',
        );
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $result = $this->factory()->build($familyAttribute->reveal(), 'en');

        self::assertNotNull($result);
        [$field] = $result;

        self::assertSame('Gewicht', $field->getLabel('en'));
        self::assertNull($field->getDescription('en'));
    }

    public function testFallsBackToAttributeKeyWhenNoTranslationExists(): void
    {
        $attribute = $this->attribute(3, 'weight', AttributeInterface::TYPE_NUMBER, [], null);
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $result = $this->factory()->build($familyAttribute->reveal(), 'en');

        self::assertNotNull($result);
        [$field] = $result;

        self::assertSame('weight', $field->getLabel('en'));
    }

    public function testBuildsUnitFieldWhenAttributeHasUnitConfigured(): void
    {
        $attribute = $this->attribute(4, 'length', AttributeInterface::TYPE_NUMBER, ['unit' => 'MILLIMETER'], 'Length');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $result = $this->factory()->build($familyAttribute->reveal(), 'en');

        self::assertNotNull($result);
        [$field, $unitField] = $result;

        self::assertSame(8, $field->getColSpan());

        self::assertNotNull($unitField);
        self::assertSame('attributes/4_unit', $unitField->getName());
        self::assertSame('single_select', $unitField->getType());
        self::assertSame('true', $unitField->getDisabledCondition());

        $values = $unitField->findOption('values');
        self::assertNotNull($values);
        self::assertSame(OptionMetadata::TYPE_COLLECTION, $values->getType());

        $valueOptions = $values->getValue();
        self::assertIsArray($valueOptions);
        self::assertCount(1, $valueOptions);
        $valueOption = $valueOptions[0];
        self::assertSame('MILLIMETER', $valueOption->getName());
        self::assertSame('MILLIMETER', $valueOption->getValue());
        self::assertSame('mm', $valueOption->getTitle('en'));
    }
}
