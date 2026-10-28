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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapper\NumberPropertyMetadataMapper;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\RangeAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeFieldFactory;
use Symfony\Component\DependencyInjection\Container;

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
        $mapperContainer = new Container();
        $mapperContainer->set('number', new NumberPropertyMetadataMapper());

        return new AttributeFieldFactory(
            new AttributeTypeRegistry([new NumberAttributeType(), new RangeAttributeType(new NumberAttributeType())]),
            $this->formMetadataLoader->reveal(),
            new MeasurementRegistry(),
            new PropertyMetadataMapperRegistry($mapperContainer),
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
     * The fragment of the range type: a "from" and a "to" number field with their own labels.
     */
    private function rangeFragment(): FormMetadata
    {
        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_range');

        foreach (['from' => 'From', 'to' => 'To'] as $name => $label) {
            $field = new FieldMetadata($name);
            $field->setType('number');
            $field->setColSpan(6);
            $field->setLabel($label, 'en');
            $fragment->addItem($field);
        }

        return $fragment;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return ObjectProphecy<AttributeInterface>
     */
    private function attribute(
        string $uuid,
        string $key,
        string $type,
        array $config,
        ?string $localeTranslationName,
        ?string $description = null,
        ?string $defaultLocale = null,
        ?string $defaultLocaleTranslationName = null,
    ): ObjectProphecy {
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getUuid()->willReturn($uuid);
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
        $familyAttribute->isRequiredFor('product')->willReturn($required);

        return $familyAttribute;
    }

    public function testReturnsNullWhenAttributeTypeIsUnknown(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000001', 'color', AttributeInterface::TYPE_TEXT, [], 'Color');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        self::assertNull($this->factory()->build($familyAttribute->reveal(), 'en', 'product'));
    }

    public function testReturnsNullWhenTemplateIsNotFormMetadata(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000001', 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn(null);

        self::assertNull($this->factory()->build($familyAttribute->reveal(), 'en', 'product'));
    }

    public function testReturnsNullWhenTemplateHasNoValueField(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000001', 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn($fragment);

        self::assertNull($this->factory()->build($familyAttribute->reveal(), 'en', 'product'));
    }

    public function testBuildsSchemaPropertyKeyedByAttributeUuid(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000007', 'weight', AttributeInterface::TYPE_NUMBER, ['min' => 0, 'max' => 10], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $property = $this->factory()->buildSchemaProperty($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($property);
        self::assertSame('0198c3e2-0000-7000-8000-000000000007', $property->getName());
        self::assertTrue($property->isMandatory());
        self::assertSame(['type' => 'number', 'minimum' => 0.0, 'maximum' => 10.0], $property->toJsonSchema());
    }

    public function testFieldAndSchemaPropertyAreNamedByAttributeUuid(): void
    {
        $uuid = '0198c3e2-0000-7000-8000-000000000001';
        $attribute = $this->attribute($uuid, 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal())->reveal();
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        self::assertSame('attribute_' . $uuid, $this->factory()->build($familyAttribute, 'en', 'product')?->getName());
        self::assertSame($uuid, $this->factory()->buildSchemaProperty($familyAttribute, 'en', 'product')?->getName());
    }

    public function testOverrideKeyIsHyphenFree(): void
    {
        self::assertSame('override_0198c3e2000070008000000000000001', AttributeFieldFactory::overrideKey('0198c3e2-0000-7000-8000-000000000001'));
    }

    public function testOverrideFieldNameCarriesTheAttributePrefix(): void
    {
        self::assertSame('attribute_override_0198c3e2000070008000000000000001', AttributeFieldFactory::overrideFieldName('0198c3e2-0000-7000-8000-000000000001'));
    }

    public function testSchemaPropertyIsNullWithoutField(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000007', 'weight', 'unknown', [], 'Weight');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        self::assertNull($this->factory()->buildSchemaProperty($familyAttribute->reveal(), 'en', 'product'));
    }

    public function testBuildsFieldWithTranslationForRequestedLocaleAndNoUnit(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000007', 'weight', AttributeInterface::TYPE_NUMBER, [], 'Weight', '<b>Heavy</b> item');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $field = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertInstanceOf(FieldMetadata::class, $field);
        self::assertSame('attribute_0198c3e2-0000-7000-8000-000000000007', $field->getName());
        self::assertSame('number', $field->getType());
        self::assertSame('Weight', $field->getLabel('en'));
        self::assertTrue($field->isRequired());
        self::assertSame('Heavy item', $field->getDescription('en'));
        self::assertSame(12, $field->getColSpan());

        $step = $field->findOption('step');
        self::assertNotNull($step);
        self::assertSame('1', $step->getValue());
    }

    public function testFallsBackToDefaultLocaleTranslationWhenRequestedLocaleHasNone(): void
    {
        $attribute = $this->attribute(
            '0198c3e2-0000-7000-8000-000000000002',
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

        $field = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($field);

        self::assertSame('Gewicht', $field->getLabel('en'));
        self::assertNull($field->getDescription('en'));
    }

    public function testFallsBackToAttributeKeyWhenNoTranslationExists(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000003', 'weight', AttributeInterface::TYPE_NUMBER, [], null);
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $field = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($field);

        self::assertSame('weight', $field->getLabel('en'));
    }

    public function testAppendsUnitSymbolToLabelWhenAttributeHasUnitConfigured(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000004', 'length', AttributeInterface::TYPE_NUMBER, ['unit' => 'MILLIMETER'], 'Length');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $field = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($field);
        self::assertSame('attribute_0198c3e2-0000-7000-8000-000000000004', $field->getName());
        self::assertSame('Length (mm)', $field->getLabel('en'));
        self::assertSame(12, $field->getColSpan());
    }

    public function testIgnoresUnknownUnitKey(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000005', 'length', AttributeInterface::TYPE_NUMBER, ['unit' => 'NOPE'], 'Length');
        $familyAttribute = $this->familyAttribute($attribute->reveal());

        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $field = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($field);
        self::assertSame('Length', $field->getLabel('en'));
    }

    public function testBuildsASectionWithTheFieldsOfAFragmentWithSeveralFields(): void
    {
        $uuid = '0198c3e2-0000-7000-8000-000000000008';
        $attribute = $this->attribute($uuid, 'temperature', AttributeInterface::TYPE_RANGE, [], 'Temperature', 'Operating range');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($this->rangeFragment());

        $section = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('attribute_' . $uuid, $section->getName());
        self::assertSame('Temperature', $section->getLabel('en'));

        $items = $section->getItems();
        self::assertSame(['from', 'to'], \array_keys($items));
        foreach (['from' => 'From', 'to' => 'To'] as $name => $label) {
            $field = $items[$name];
            self::assertInstanceOf(FieldMetadata::class, $field);
            self::assertSame('number', $field->getType());
            self::assertSame($label, $field->getLabel('en'));
            self::assertSame(6, $field->getColSpan());
            self::assertTrue($field->isRequired());
            self::assertNull($field->getDescription('en'));
        }
    }

    public function testTheSectionOfAMultiFieldAttributeAppendsTheUnitAndIsNotRequiredByDefault(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000008', 'temperature', AttributeInterface::TYPE_RANGE, ['unit' => 'MILLIMETER'], null);
        $familyAttribute = $this->familyAttribute($attribute->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($this->rangeFragment());

        $section = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('temperature (mm)', $section->getLabel('en'));
        foreach ($section->getItems() as $field) {
            self::assertInstanceOf(FieldMetadata::class, $field);
            self::assertFalse($field->isRequired());
            self::assertSame('from' === $field->getName() ? 'From' : 'To', $field->getLabel('en'), 'The fields keep the labels of the fragment.');
        }
    }

    public function testTheTypeConfiguresEveryFieldOfAMultiFieldAttribute(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000008', 'temperature', AttributeInterface::TYPE_RANGE, ['min' => -20, 'max' => 60, 'step' => 0.5], 'Temperature');
        $familyAttribute = $this->familyAttribute($attribute->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($this->rangeFragment());

        $section = $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertCount(2, $section->getItems());
        foreach ($section->getItems() as $field) {
            self::assertInstanceOf(FieldMetadata::class, $field);
            self::assertSame('-20', $field->findOption('min')?->getValue());
            self::assertSame('60', $field->findOption('max')?->getValue());
            self::assertSame('0.5', $field->findOption('step')?->getValue());
        }
    }

    public function testBuildingASectionDoesNotChangeTheFragment(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000008', 'temperature', AttributeInterface::TYPE_RANGE, ['min' => 0], 'Temperature');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);
        $fragment = $this->rangeFragment();
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($fragment);

        $this->factory()->build($familyAttribute->reveal(), 'en', 'product');

        foreach ($fragment->getItems() as $template) {
            self::assertInstanceOf(FieldMetadata::class, $template);
            self::assertFalse($template->isRequired());
            self::assertSame([], $template->getOptions());
        }
    }

    public function testRequiredMultiFieldAttributeSchemaIsAnObjectOfTheFieldsWhichAreNotRequiredThemselves(): void
    {
        $uuid = '0198c3e2-0000-7000-8000-000000000008';
        $attribute = $this->attribute($uuid, 'temperature', AttributeInterface::TYPE_RANGE, ['min' => -20, 'max' => 60], 'Temperature');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($this->rangeFragment());

        $property = $this->factory()->buildSchemaProperty($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($property);
        self::assertSame($uuid, $property->getName());
        self::assertTrue($property->isMandatory());
        self::assertSame($this->objectSchema(), $property->toJsonSchema());
    }

    public function testOptionalMultiFieldAttributeSchemaAlsoAllowsNull(): void
    {
        $uuid = '0198c3e2-0000-7000-8000-000000000008';
        $attribute = $this->attribute($uuid, 'temperature', AttributeInterface::TYPE_RANGE, ['min' => -20, 'max' => 60], 'Temperature');
        $familyAttribute = $this->familyAttribute($attribute->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($this->rangeFragment());

        $property = $this->factory()->buildSchemaProperty($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($property);
        self::assertSame($uuid, $property->getName());
        self::assertFalse($property->isMandatory());
        self::assertSame(['anyOf' => [['type' => 'null'], $this->objectSchema()]], $property->toJsonSchema());
    }

    public function testMultiFieldAttributeSchemaWithoutConfigHasUnboundedProperties(): void
    {
        $attribute = $this->attribute('0198c3e2-0000-7000-8000-000000000008', 'temperature', AttributeInterface::TYPE_RANGE, [], 'Temperature');
        $familyAttribute = $this->familyAttribute($attribute->reveal(), true);
        $this->formMetadataLoader->getMetadata('product_attribute_range', 'en', [])->willReturn($this->rangeFragment());

        $property = $this->factory()->buildSchemaProperty($familyAttribute->reveal(), 'en', 'product');

        self::assertNotNull($property);
        $bound = ['anyOf' => [['type' => 'null'], ['type' => 'number']]];
        self::assertSame(['type' => 'object', 'properties' => ['from' => $bound, 'to' => $bound]], $property->toJsonSchema());
    }

    /**
     * @return array<string, mixed>
     */
    private function objectSchema(): array
    {
        $bound = ['anyOf' => [['type' => 'null'], ['type' => 'number', 'minimum' => -20.0, 'maximum' => 60.0]]];

        return ['type' => 'object', 'properties' => ['from' => $bound, 'to' => $bound]];
    }
}
