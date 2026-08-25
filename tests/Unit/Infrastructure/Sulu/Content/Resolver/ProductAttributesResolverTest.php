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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductAttributesResolver;

#[CoversClass(ProductAttributesResolver::class)]
class ProductAttributesResolverTest extends TestCase
{
    private ProductAttributesResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ProductAttributesResolver(new MeasurementRegistry());
    }

    public function testReturnsNullForNonProductContent(): void
    {
        $content = $this->createStub(DimensionContentInterface::class);

        self::assertNull($this->resolver->resolve($content));
    }

    public function testReturnsNullWhenResolvedAsAReference(): void
    {
        $content = $this->createContent();

        self::assertNull($this->resolver->resolve($content, ['title' => 'title']));
    }

    public function testReturnsAFlatMapKeyedByAttributeKey(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('housing', 'Gehäuse', $group, 2), 'Zink');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $attributes = $this->resolveAttributes($content);

        self::assertSame(['housing', 'weight'], \array_keys($attributes));
        self::assertSame('Gewicht', $attributes['weight']['label']);
        self::assertSame('48 g', $attributes['weight']['formattedValue']);
        self::assertSame(1, $attributes['weight']['position']);
        self::assertSame(AttributeInterface::TYPE_TEXT, $attributes['weight']['type']);
    }

    public function testEachAttributeCarriesItsGroup(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(7, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $attributes = $this->resolveAttributes($content);

        self::assertSame(['key' => '7', 'label' => 'Mechanische Daten'], $attributes['weight']['group']);
    }

    public function testAttributesFormattingToNothingAreDropped(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');
        $this->addTextValue($content, $this->createAttribute('housing', 'Gehäuse', $group, 2), '');

        self::assertSame(['weight'], \array_keys($this->resolveAttributes($content)));
    }

    public function testOptionValuesResolveToTheTranslatedOptionName(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);

        $option = new AttributeOption($attribute, 'black');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Schwarz'));

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setAttributeOptionKey('black');
        $value->setAttributeOption($option);
        $content->addAttribute($value);

        $attributes = $this->resolveAttributes($content);
        self::assertSame('black', $attributes['colour']['value']);
        self::assertSame('Schwarz', $attributes['colour']['formattedValue']);
    }

    public function testDisplayFormatIgnoredForOptionsAttribute(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $attribute->setConfig(['displayFormat' => 'ca. %value%']);

        $option = new AttributeOption($attribute, 'black');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Schwarz'));

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setAttributeOptionKey('black');
        $value->setAttributeOption($option);
        $content->addAttribute($value);

        self::assertSame('Schwarz', $this->resolveAttributes($content)['colour']['formattedValue']);
    }

    public function testNumbersUseTheAttributesDisplayFormatAndUnit(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('diameter', 'Durchmesser', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber(16.0);
        $content->addAttribute($value);

        self::assertSame('16 mm', $this->resolveAttributes($content)['diameter']['formattedValue']);
    }

    public function testDisplayFormatAppliesToTextAttributes(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setConfig(['displayFormat' => 'ca. %value%']);

        $this->addTextValue($content, $attribute, 'Rot');

        $attributes = $this->resolveAttributes($content);
        self::assertSame('Rot', $attributes['colour']['value']);
        self::assertSame('ca. Rot', $attributes['colour']['formattedValue']);
    }

    public function testUnitPlaceholderRemovedWithoutConfiguredUnit(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('weight', 'Gewicht', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setConfig(['displayFormat' => '%value% %unit%']);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber(2.0);
        $content->addAttribute($value);

        self::assertSame('2', $this->resolveAttributes($content)['weight']['formattedValue']);
    }

    public function testUnitPlaceholderRemovedForUnresolvableUnitKey(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('weight', 'Gewicht', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'NOT_A_UNIT']);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber(2.0);
        $content->addAttribute($value);

        self::assertSame('2', $this->resolveAttributes($content)['weight']['formattedValue']);
    }

    public function testDisplayFormatWithoutValueTokenIsReturnedLiterally(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('weight', 'Gewicht', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setConfig(['displayFormat' => 'on request']);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber(42.5);
        $content->addAttribute($value);

        self::assertSame('on request', $this->resolveAttributes($content)['weight']['formattedValue']);
    }

    public function testDatesAreFormattedForTheContentLocale(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('released', 'Erschienen', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_DATE);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber((float) (new \DateTimeImmutable('2024-03-05'))->getTimestamp());
        $content->addAttribute($value);

        self::assertSame('05.03.2024', $this->resolveAttributes($content)['released']['formattedValue']);
    }

    public function testUnrecognisedAttributeTypeIsDropped(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('custom', 'Individuell', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType('unknown_type');

        $this->addTextValue($content, $attribute, 'some-value');

        // an attribute type the resolver does not recognise formats to nothing and is dropped
        self::assertSame([], $this->resolveAttributes($content));
    }

    public function testGroupLabelFallsBackToTheGroupIdWithoutATranslationInTheLocale(): void
    {
        $content = $this->createContent('en');
        $group = $this->createGroup(3, 'Mechanische Daten'); // translated only in 'de'

        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        self::assertSame('3', $this->resolveAttributes($content)['weight']['group']['label']);
    }

    public function testAttributeLabelFallsBackToTheAttributeKeyWithoutATranslationInTheLocale(): void
    {
        $content = $this->createContent('en');
        $attribute = $this->createAttribute('weight', 'Gewicht', $this->createGroup(1, 'Eins'), 1); // translated only in 'de'

        $this->addTextValue($content, $attribute, '48 g');

        self::assertSame('weight', $this->resolveAttributes($content)['weight']['label']);
    }

    public function testOptionNameFallsBackToTheOptionKeyWithoutATranslationInTheLocale(): void
    {
        $content = $this->createContent('en');
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);

        $option = new AttributeOption($attribute, 'black');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Schwarz')); // translated only in 'de'

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setAttributeOptionKey('black');
        $value->setAttributeOption($option);
        $content->addAttribute($value);

        self::assertSame('black', $this->resolveAttributes($content)['colour']['formattedValue']);
    }

    public function testDateValueWithoutATimestampIsDropped(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('released', 'Erschienen', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_DATE);

        // no setNumber() call: getNumber() stays null, so formatDate() has nothing to format
        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $content->addAttribute($value);

        self::assertSame([], $this->resolveAttributes($content));
    }

    /**
     * @return array<string, array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}>
     */
    private function resolveAttributes(ProductDimensionContent $content): array
    {
        $contentView = $this->resolver->resolve($content);
        self::assertNotNull($contentView);

        /** @var array<string, array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}> $attributes */
        $attributes = $contentView->getContent();

        return $attributes;
    }

    private function createContent(string $locale = 'de'): ProductDimensionContent
    {
        $content = new ProductDimensionContent(new Product());
        $content->setLocale($locale);

        return $content;
    }

    private function createGroup(int $id, string $name): AttributeGroup
    {
        $group = new AttributeGroup();
        // ids are database-generated; the resolver renders them, so tests must set them
        (new \ReflectionProperty(AttributeGroup::class, 'id'))->setValue($group, $id);
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', $name));

        return $group;
    }

    private function createAttribute(string $key, string $name, AttributeGroup $group, int $position): Attribute
    {
        $attribute = new Attribute($group);
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setPosition($position);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', $name));

        return $attribute;
    }

    private function addTextValue(ProductDimensionContent $content, Attribute $attribute, string $text): void
    {
        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setText($text);
        $content->addAttribute($value);
    }
}
