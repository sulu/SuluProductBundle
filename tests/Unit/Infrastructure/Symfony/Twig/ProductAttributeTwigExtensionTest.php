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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\AttributeValueView;
use Sulu\Product\Application\AttributeType\AttributeValueViewFactory;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Application\AttributeType\RangeAttributeType;
use Sulu\Product\Application\AttributeType\TextAttributeType;
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
use Sulu\Product\Infrastructure\Symfony\Twig\ProductAttributeTwigExtension;
use Twig\TwigFunction;

#[CoversClass(ProductAttributeTwigExtension::class)]
class ProductAttributeTwigExtensionTest extends TestCase
{
    private function extension(?string $currentLocale = null): ProductAttributeTwigExtension
    {
        $requestAnalyzer = $this->createStub(RequestAnalyzerInterface::class);
        $requestAnalyzer->method('getCurrentLocalization')->willReturn(
            null === $currentLocale ? null : new Localization($currentLocale),
        );

        return new ProductAttributeTwigExtension(new MeasurementRegistry(), $requestAnalyzer);
    }

    /**
     * @return array<string, array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}>
     */
    private function attributesOf(ProductDimensionContent $content, string $locale = 'de'): array
    {
        $flat = [];
        foreach ($this->extension()->groupAttributes($this->views($content), $locale) as $group) {
            foreach ($group['attributes'] as $key => $attribute) {
                $flat[$key] = $attribute;
            }
        }

        return $flat;
    }

    /**
     * @return array<string, AttributeValueView>
     */
    private function views(ProductDimensionContent $content): array
    {
        $factory = new AttributeValueViewFactory(new AttributeTypeRegistry([
            new NumberAttributeType(),
            new TextAttributeType(),
            new DateAttributeType(),
            new OptionsAttributeType(),
            new RangeAttributeType(),
        ]));

        return $factory->createMap($content->getAttributes());
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
        // ids are database-generated; the filter renders them, so tests must set them
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

    public function testMergeAttributesAddsTheVariantsValuesOverTheProducts(): void
    {
        $product = $this->contentWithValues(['housing' => 'Zink', 'colour' => 'stale']);
        $variant = $this->contentWithValues(['colour' => 'black']);

        $merged = $this->extension()->mergeAttributes($this->views($product), $this->views($variant));

        $this->assertSame(['housing', 'colour'], \array_keys($merged));
        $this->assertSame('black', $merged['colour']->getValue());
        $this->assertSame(['housing', 'colour'], \array_keys($this->extension()->mergeAttributes($this->views($product))));
        $this->assertSame(['colour'], \array_keys($this->extension()->mergeAttributes([], $this->views($variant))));
        $this->assertSame([], $this->extension()->mergeAttributes([], []));
        $this->assertSame(['sulu_product_merge_attributes'], \array_map(
            static fn (TwigFunction $function): string => $function->getName(),
            $this->extension()->getFunctions(),
        ));
    }

    /**
     * @param array<string, string> $values
     */
    private function contentWithValues(array $values): ProductDimensionContent
    {
        $content = new ProductDimensionContent(new Product());
        foreach ($values as $key => $text) {
            $attribute = new Attribute(new AttributeGroup());
            $attribute->setKey($key);
            $attribute->setType(AttributeInterface::TYPE_TEXT);

            $value = new ProductAttributeValue($content, $attribute, $key);
            $value->setText($text);
            $content->addAttribute($value);
        }

        return $content;
    }

    public function testGroupsAttributesByTheirGroup(): void
    {
        $content = $this->createContent();
        $technical = $this->createGroup(1, 'Technische Daten');
        $mechanical = $this->createGroup(2, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('impedance', 'Impedanz', $technical, 1), '50 Ohm');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $mechanical, 1), '48 g');

        $groups = $this->extension()->groupAttributes($this->views($content), 'de');

        self::assertSame(['1', '2'], \array_column($groups, 'key'));
        self::assertSame('Technische Daten', $groups[0]['label']);
        self::assertSame(['impedance'], \array_keys($groups[0]['attributes']));
        self::assertSame(['weight'], \array_keys($groups[1]['attributes']));
    }

    public function testGroupsSortNumericallyByKey(): void
    {
        $content = $this->createContent();

        $this->addTextValue($content, $this->createAttribute('a', 'A', $this->createGroup(10, 'Ten'), 1), 'x');
        $this->addTextValue($content, $this->createAttribute('b', 'B', $this->createGroup(9, 'Nine'), 1), 'y');

        $groups = $this->extension()->groupAttributes($this->views($content), 'de');

        self::assertSame(['9', '10'], \array_column($groups, 'key'));
    }

    public function testAttributesSortByPositionWithinAGroup(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Technische Daten');

        $this->addTextValue($content, $this->createAttribute('housing', 'Gehäuse', $group, 2), 'Zink');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $groups = $this->extension()->groupAttributes($this->views($content), 'de');

        self::assertSame(['weight', 'housing'], \array_keys($groups[0]['attributes']));
    }

    public function testEmptyInputYieldsNoGroups(): void
    {
        self::assertSame([], $this->extension()->groupAttributes([], 'de'));
    }

    public function testEachAttributeCarriesItsGroupAndMetadata(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(7, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $weight = $this->attributesOf($content)['weight'];

        self::assertSame('weight', $weight['key']);
        self::assertSame('Gewicht', $weight['label']);
        self::assertSame(AttributeInterface::TYPE_TEXT, $weight['type']);
        self::assertSame(1, $weight['position']);
        self::assertSame(['key' => '7', 'label' => 'Mechanische Daten'], $weight['group']);
    }

    public function testAttributesFormattingToNothingAreDropped(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');
        $this->addTextValue($content, $this->createAttribute('housing', 'Gehäuse', $group, 2), '');

        self::assertSame(['weight'], \array_keys($this->attributesOf($content)));
    }

    public function testOptionValuesResolveToTheTranslatedOptionName(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);

        $option = new AttributeOption($attribute, 'black');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Schwarz'));
        $attribute->addOption($option);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey(), $option);
        $content->addAttribute($value);

        $colour = $this->attributesOf($content)['colour'];
        self::assertSame('black', $colour['value']);
        self::assertSame('Schwarz', $colour['formattedValue']);
    }

    public function testOptionValueWhoseOptionWasDeletedIsDropped(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);

        // deleting an option sets the value's link to null
        $content->addAttribute(new ProductAttributeValue($content, $attribute, $attribute->getKey()));

        self::assertSame([], $this->attributesOf($content));
    }

    public function testDisplayFormatIgnoredForOptionsAttribute(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $attribute->setConfig(['displayFormat' => 'ca. %value%']);

        $option = new AttributeOption($attribute, 'black');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Schwarz'));
        $attribute->addOption($option);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey(), $option);
        $content->addAttribute($value);

        self::assertSame('Schwarz', $this->attributesOf($content)['colour']['formattedValue']);
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

        self::assertSame('16 mm', $this->attributesOf($content)['diameter']['formattedValue']);
    }

    public function testDisplayFormatAppliesToTextAttributes(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setConfig(['displayFormat' => 'ca. %value%']);

        $this->addTextValue($content, $attribute, 'Rot');

        $colour = $this->attributesOf($content)['colour'];
        self::assertSame('Rot', $colour['value']);
        self::assertSame('ca. Rot', $colour['formattedValue']);
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

        self::assertSame('2', $this->attributesOf($content)['weight']['formattedValue']);
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

        self::assertSame('2', $this->attributesOf($content)['weight']['formattedValue']);
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

        self::assertSame('on request', $this->attributesOf($content)['weight']['formattedValue']);
    }

    private function addRangeValue(ProductDimensionContent $content, Attribute $attribute, ?float $from, ?float $to): void
    {
        $attribute->setType(AttributeInterface::TYPE_RANGE);

        foreach (['from' => $from, 'to' => $to] as $valueKey => $number) {
            $row = new ProductAttributeValue($content, $attribute, $attribute->getKey(), valueKey: $valueKey);
            $row->setNumber($number);
            $content->addAttribute($row);
        }
    }

    public function testRangesRenderBothBoundsWithoutADisplayFormat(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('temperature', 'Temperatur', $this->createGroup(1, 'Eins'), 1);
        $this->addRangeValue($content, $attribute, -20.0, 60.5);

        $temperature = $this->attributesOf($content)['temperature'];
        self::assertSame(['from' => -20.0, 'to' => 60.5], $temperature['value']);
        self::assertSame('-20 – 60.5', $temperature['formattedValue']);
    }

    public function testRangesUseTheAttributesDisplayFormatAndUnit(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('temperature', 'Temperatur', $this->createGroup(1, 'Eins'), 1);
        $attribute->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'CELSIUS']);
        $this->addRangeValue($content, $attribute, -20.0, 60.0);

        self::assertSame('-20 – 60 °C', $this->attributesOf($content)['temperature']['formattedValue']);
    }

    public function testRangeDisplayFormatPlacesEachBound(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('voltage', 'Spannung', $this->createGroup(1, 'Eins'), 1);
        $attribute->setConfig(['displayFormat' => 'from %from% %unit% up to %to% %unit%', 'unit' => 'VOLT']);
        $this->addRangeValue($content, $attribute, 100.0, 240.0);

        self::assertSame('from 100 V up to 240 V', $this->attributesOf($content)['voltage']['formattedValue']);
    }

    public function testRangeMissingABoundIsDropped(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('temperature', 'Temperatur', $this->createGroup(1, 'Eins'), 1);
        $this->addRangeValue($content, $attribute, -20.0, null);

        self::assertSame([], $this->attributesOf($content));
    }

    public function testDatesAreFormattedForTheRequestedLocale(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('released', 'Erschienen', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_DATE);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber((float) (new \DateTimeImmutable('2024-03-05'))->getTimestamp());
        $content->addAttribute($value);

        self::assertSame('05.03.2024', $this->attributesOf($content)['released']['formattedValue']);
    }

    public function testDatesUseTheAttributesDisplayFormatInTheRequestedLocale(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('released', 'Erschienen', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_DATE);
        $attribute->setConfig(['displayFormat' => 'MMMM yyyy']);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber((float) (new \DateTimeImmutable('2024-03-05'))->getTimestamp());
        $content->addAttribute($value);

        self::assertSame('März 2024', $this->attributesOf($content)['released']['formattedValue']);
    }

    public function testDatesRenderTheStoredUtcDayWhateverTheServerTimezone(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('released', 'Erschienen', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_DATE);
        $attribute->setConfig(['displayFormat' => 'MMMM yyyy']);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setNumber((float) (new \DateTimeImmutable('2024-03-01', new \DateTimeZone('UTC')))->getTimestamp());
        $content->addAttribute($value);

        $defaultTimezone = \date_default_timezone_get();
        \date_default_timezone_set('America/New_York');

        try {
            self::assertSame('März 2024', $this->attributesOf($content)['released']['formattedValue']);
        } finally {
            \date_default_timezone_set($defaultTimezone);
        }
    }

    public function testDateValueWithoutATimestampIsDropped(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('released', 'Erschienen', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_DATE);

        // no setNumber() call: getNumber() stays null, so formatDate() has nothing to format
        $content->addAttribute(new ProductAttributeValue($content, $attribute, $attribute->getKey()));

        self::assertSame([], $this->attributesOf($content));
    }

    public function testUnrecognisedAttributeTypeIsDropped(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('custom', 'Individuell', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType('unknown_type');

        $this->addTextValue($content, $attribute, 'some-value');

        self::assertSame([], $this->attributesOf($content));
    }

    public function testGroupLabelFallsBackToTheGroupIdWithoutATranslationInTheLocale(): void
    {
        $content = $this->createContent('en');
        $group = $this->createGroup(3, 'Mechanische Daten'); // translated only in 'de'

        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        self::assertSame('3', $this->attributesOf($content, 'en')['weight']['group']['label']);
    }

    public function testAttributeLabelFallsBackToTheAttributeKeyWithoutATranslationInTheLocale(): void
    {
        $content = $this->createContent('en');
        $attribute = $this->createAttribute('weight', 'Gewicht', $this->createGroup(1, 'Eins'), 1); // 'de' only

        $this->addTextValue($content, $attribute, '48 g');

        self::assertSame('weight', $this->attributesOf($content, 'en')['weight']['label']);
    }

    public function testOptionNameFallsBackToTheOptionKeyWithoutATranslationInTheLocale(): void
    {
        $content = $this->createContent('en');
        $attribute = $this->createAttribute('colour', 'Farbe', $this->createGroup(1, 'Eins'), 1);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);

        $option = new AttributeOption($attribute, 'black');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Schwarz')); // 'de' only
        $attribute->addOption($option);

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey(), $option);
        $content->addAttribute($value);

        self::assertSame('black', $this->attributesOf($content, 'en')['colour']['formattedValue']);
    }

    public function testLocaleFallsBackToTheCurrentRequestWhenNoneIsGiven(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $groups = $this->extension('de')->groupAttributes($this->views($content));

        self::assertSame('Gewicht', $groups[0]['attributes']['weight']['label']);
    }

    public function testYieldsNoGroupsWithoutALocaleToTranslateWith(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        self::assertSame([], $this->extension()->groupAttributes($this->views($content)));
    }

    public function testFilterIsRegistered(): void
    {
        $names = \array_map(
            static fn (\Twig\TwigFilter $filter): string => $filter->getName(),
            $this->extension()->getFilters(),
        );

        self::assertContains('sulu_product_attribute_groups', $names);
        self::assertContains('sulu_product_format_attribute_value', $names);
    }

    public function testFormatValueFormatsASingleValue(): void
    {
        $content = $this->createContent();
        $attribute = $this->createAttribute('voltage', 'Spannung', $this->createGroup(1, 'Elektrisch'), 1);
        $attribute->setConfig(['displayFormat' => '< %value% V']);
        $this->addTextValue($content, $attribute, '50');

        $attributeValue = \current($this->views($content));
        self::assertInstanceOf(AttributeValueView::class, $attributeValue);

        self::assertSame('< 50 V', $this->extension()->formatValue($attributeValue, 'de'));
    }

    public function testFormatValueFallsBackToTheCurrentRequestsLocale(): void
    {
        $content = $this->createContent();
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $this->createGroup(1, 'Mechanisch'), 1), '48 g');

        $attributeValue = \current($this->views($content));
        self::assertInstanceOf(AttributeValueView::class, $attributeValue);

        self::assertSame('48 g', $this->extension('de')->formatValue($attributeValue));
        self::assertNull($this->extension()->formatValue($attributeValue), 'no locale to format with');
    }
}
