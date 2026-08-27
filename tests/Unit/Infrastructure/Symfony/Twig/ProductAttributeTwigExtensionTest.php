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
        foreach ($this->extension()->groupAttributes($content->getAttributes(), $locale) as $group) {
            foreach ($group['attributes'] as $key => $attribute) {
                $flat[$key] = $attribute;
            }
        }

        return $flat;
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

    public function testGroupsAttributesByTheirGroup(): void
    {
        $content = $this->createContent();
        $technical = $this->createGroup(1, 'Technische Daten');
        $mechanical = $this->createGroup(2, 'Mechanische Daten');

        $this->addTextValue($content, $this->createAttribute('impedance', 'Impedanz', $technical, 1), '50 Ohm');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $mechanical, 1), '48 g');

        $groups = $this->extension()->groupAttributes($content->getAttributes(), 'de');

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

        $groups = $this->extension()->groupAttributes($content->getAttributes(), 'de');

        self::assertSame(['9', '10'], \array_column($groups, 'key'));
    }

    public function testAttributesSortByPositionWithinAGroup(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Technische Daten');

        $this->addTextValue($content, $this->createAttribute('housing', 'Gehäuse', $group, 2), 'Zink');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $groups = $this->extension()->groupAttributes($content->getAttributes(), 'de');

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

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setAttributeOptionKey('black');
        $value->setAttributeOption($option);
        $content->addAttribute($value);

        $colour = $this->attributesOf($content)['colour'];
        self::assertSame('black', $colour['value']);
        self::assertSame('Schwarz', $colour['formattedValue']);
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

        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setAttributeOptionKey('black');
        $value->setAttributeOption($option);
        $content->addAttribute($value);

        self::assertSame('black', $this->attributesOf($content, 'en')['colour']['formattedValue']);
    }

    public function testLocaleFallsBackToTheCurrentRequestWhenNoneIsGiven(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        $groups = $this->extension('de')->groupAttributes($content->getAttributes());

        self::assertSame('Gewicht', $groups[0]['attributes']['weight']['label']);
    }

    public function testYieldsNoGroupsWithoutALocaleToTranslateWith(): void
    {
        $content = $this->createContent();
        $group = $this->createGroup(1, 'Mechanische Daten');
        $this->addTextValue($content, $this->createAttribute('weight', 'Gewicht', $group, 1), '48 g');

        self::assertSame([], $this->extension()->groupAttributes($content->getAttributes()));
    }

    public function testFilterIsRegistered(): void
    {
        $names = \array_map(
            static fn (\Twig\TwigFilter $filter): string => $filter->getName(),
            $this->extension()->getFilters(),
        );

        self::assertContains('sulu_product_attribute_groups', $names);
    }
}
