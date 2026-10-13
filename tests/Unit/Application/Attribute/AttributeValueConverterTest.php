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

namespace Sulu\Product\Tests\Unit\Application\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Product\Application\Attribute\AttributeValueConverter;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(AttributeValueConverter::class)]
class AttributeValueConverterTest extends TestCase
{
    use ProphecyTrait;

    private const MULTI_SELECT = 'multi_select_attribute';
    private const DIMENSIONS = 'dimensions';
    private const UNSUPPORTED = 'unsupported_attribute';
    private const NO_FRAGMENT = 'no_fragment_attribute';

    /**
     * The fields of the form fragment of every attribute type used here, by field name.
     *
     * @var array<string, array<string, string>>
     */
    private const FRAGMENTS = [
        AttributeInterface::TYPE_NUMBER => ['value' => 'number'],
        AttributeInterface::TYPE_DATE => ['value' => 'date'],
        AttributeInterface::TYPE_TEXT => ['value' => 'text_line'],
        AttributeInterface::TYPE_OPTIONS => ['value' => 'single_select'],
        AttributeInterface::TYPE_BOOLEAN => ['value' => 'single_select'],
        self::MULTI_SELECT => ['value' => 'select'],
        self::DIMENSIONS => ['width' => 'number', 'height' => 'number'],
        self::UNSUPPORTED => ['value' => 'checkbox'],
    ];

    public function testGetFieldsReturnsTheFieldTypesByName(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);

        self::assertSame(['width' => 'number', 'height' => 'number'], $this->converter()->getFields($attribute));
    }

    public function testGetFieldsLoadsTheFragmentOfTheAttributeType(): void
    {
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_number', 'en', [])->willReturn($this->fragment(['value' => 'number']))->shouldBeCalledOnce();

        (new AttributeValueConverter($loader->reveal()))->getFields($this->attribute(AttributeInterface::TYPE_NUMBER));
    }

    public function testGetFieldsWithoutFragmentIsEmpty(): void
    {
        self::assertSame([], $this->converter()->getFields($this->attribute(self::NO_FRAGMENT)));
    }

    public function testGetFieldsSkipsSections(): void
    {
        $section = new SectionMetadata('section');
        $section->addItem($this->field('inner', 'number'));

        $fragment = new FormMetadata();
        $fragment->addItem($this->field('value', 'number'));
        $fragment->addItem($section);

        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_custom', 'en', [])->willReturn($fragment);

        $attribute = $this->attribute('custom');

        self::assertSame(['value' => 'number'], (new AttributeValueConverter($loader->reveal()))->getFields($attribute));
    }

    public function testGetFieldsRejectsAMultiSelectBesideOtherFields(): void
    {
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_custom', 'en', [])->willReturn($this->fragment(['name' => 'text_line', 'tags' => 'select']));

        $this->expectException(\LogicException::class);

        (new AttributeValueConverter($loader->reveal()))->getFields($this->attribute('custom'));
    }

    public function testGetFieldsLoadsTheFragmentOncePerAttributeType(): void
    {
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_number', 'en', [])->willReturn($this->fragment(['value' => 'number']))->shouldBeCalledOnce();
        $loader->getMetadata('product_attribute_text', 'en', [])->willReturn($this->fragment(['value' => 'text_line']))->shouldBeCalledOnce();
        $loader->getMetadata('product_attribute_missing', 'en', [])->willReturn(null)->shouldBeCalledOnce();
        $converter = new AttributeValueConverter($loader->reveal());

        foreach ([AttributeInterface::TYPE_NUMBER, AttributeInterface::TYPE_NUMBER, AttributeInterface::TYPE_TEXT, 'missing', 'missing'] as $type) {
            $converter->getFields($this->attribute($type));
        }

        self::assertSame(['value' => 'number'], $converter->getFields($this->attribute(AttributeInterface::TYPE_NUMBER)));
        self::assertSame([], $converter->getFields($this->attribute('missing')));
    }

    /**
     * @return iterable<string, array{string, array{number?: float|null, text?: string|null, option?: string|null}, mixed}>
     */
    public static function provideSingleValueRows(): iterable
    {
        $timestamp = (float) (new \DateTimeImmutable('2024-02-29', new \DateTimeZone('UTC')))->getTimestamp();

        yield 'number' => [AttributeInterface::TYPE_NUMBER, ['number' => 42.5], 42.5];
        yield 'number zero' => [AttributeInterface::TYPE_NUMBER, ['number' => 0.0], 0.0];
        yield 'number without a value' => [AttributeInterface::TYPE_NUMBER, [], null];
        yield 'date' => [AttributeInterface::TYPE_DATE, ['number' => $timestamp], '2024-02-29'];
        yield 'date without a value' => [AttributeInterface::TYPE_DATE, [], null];
        yield 'text' => [AttributeInterface::TYPE_TEXT, ['text' => 'hello'], 'hello'];
        yield 'text without a value' => [AttributeInterface::TYPE_TEXT, [], null];
        yield 'option' => [AttributeInterface::TYPE_OPTIONS, ['option' => 'red'], 'red'];
        yield 'option without a value' => [AttributeInterface::TYPE_OPTIONS, [], null];
        yield 'boolean true' => [AttributeInterface::TYPE_BOOLEAN, ['number' => 1.0], 'true'];
        yield 'boolean false' => [AttributeInterface::TYPE_BOOLEAN, ['number' => 0.0], 'false'];
        yield 'boolean without a value' => [AttributeInterface::TYPE_BOOLEAN, [], null];
    }

    /**
     * @param array{number?: float|null, text?: string|null, option?: string|null} $columns
     */
    #[DataProvider('provideSingleValueRows')]
    public function testReadSingleField(string $type, array $columns, mixed $expected): void
    {
        $attribute = $this->attribute($type);

        self::assertSame($expected, $this->converter()->read($attribute, [$this->row($attribute, 'value', $columns)]));
    }

    #[DataProvider('provideSingleValueRows')]
    public function testReadSingleFieldWithoutRowIsNull(string $type): void
    {
        self::assertNull($this->converter()->read($this->attribute($type), []));
    }

    public function testReadSingleFieldFindsTheRowOfItsName(): void
    {
        $attribute = $this->attribute('amount');
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_amount', 'en', [])->willReturn($this->fragment(['amount' => 'number']));

        $rows = [$this->row($attribute, 'other', ['number' => 1.0]), $this->row($attribute, 'amount', ['number' => 2.0])];

        self::assertSame(2.0, (new AttributeValueConverter($loader->reveal()))->read($attribute, $rows));
    }

    public function testReadWithoutFragmentIsNull(): void
    {
        $attribute = $this->attribute(self::NO_FRAGMENT);

        self::assertNull($this->converter()->read($attribute, [$this->row($attribute, 'value', ['number' => 1.0])]));
    }

    public function testReadMultiSelectReturnsTheOptionKeysInRowOrder(): void
    {
        $attribute = $this->attribute(self::MULTI_SELECT);
        $rows = [
            $this->row($attribute, 'green', ['option' => 'green']),
            $this->row($attribute, 'red', ['option' => 'red']),
        ];

        self::assertSame(['red', 'green'], $this->converter()->read($attribute, $rows));
    }

    public function testReadMultiSelectKeepsAnOptionNamedZero(): void
    {
        $attribute = $this->attribute(self::MULTI_SELECT);
        $attribute->addOption(new AttributeOption($attribute, '0'));
        $rows = [$this->row($attribute, '0', ['option' => '0']), $this->row($attribute, 'green', ['option' => 'green'])];

        self::assertSame(['green', '0'], $this->converter()->read($attribute, $rows));
        self::assertSame(['0'], $this->converter()->read($attribute, [$rows[0]]));
    }

    public function testReadMultiSelectWithoutRowsIsNull(): void
    {
        self::assertNull($this->converter()->read($this->attribute(self::MULTI_SELECT), []));
    }

    public function testReadMultiSelectSkipsRowsWithoutOption(): void
    {
        $attribute = $this->attribute(self::MULTI_SELECT);
        $rows = [
            $this->row($attribute, 'green', ['option' => 'green']),
            $this->row($attribute, 'deleted', []),
            $this->row($attribute, 'red', ['option' => 'red']),
        ];

        self::assertSame(['red', 'green'], $this->converter()->read($attribute, $rows));
    }

    public function testReadMultiSelectWithOnlyRowsWithoutOptionIsNull(): void
    {
        $attribute = $this->attribute(self::MULTI_SELECT);
        $rows = [$this->row($attribute, 'deleted', []), $this->row($attribute, 'gone', [])];

        self::assertNull($this->converter()->read($attribute, $rows));
    }

    public function testReadObjectReturnsTheValueOfEveryField(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);
        $rows = [
            $this->row($attribute, 'width', ['number' => 10.0]),
            $this->row($attribute, 'height', ['number' => 20.5]),
        ];

        self::assertSame(['width' => 10.0, 'height' => 20.5], $this->converter()->read($attribute, $rows));
    }

    public function testReadObjectIgnoresTheOrderOfTheRows(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);
        $rows = [
            $this->row($attribute, 'height', ['number' => 20.5]),
            $this->row($attribute, 'width', ['number' => 10.0]),
        ];

        self::assertSame(['width' => 10.0, 'height' => 20.5], $this->converter()->read($attribute, $rows));
    }

    public function testReadObjectFromAGenerator(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);
        $rows = (function() use ($attribute): \Generator {
            yield $this->row($attribute, 'height', ['number' => 20.5]);
            yield $this->row($attribute, 'width', ['number' => 10.0]);
        })();

        self::assertSame(['width' => 10.0, 'height' => 20.5], $this->converter()->read($attribute, $rows));
    }

    public function testReadObjectWithPartialValuesKeepsTheMissingOnesNull(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);

        self::assertSame(
            ['width' => null, 'height' => 20.5],
            $this->converter()->read($attribute, [$this->row($attribute, 'height', ['number' => 20.5])]),
        );
    }

    public function testReadObjectWithoutAnyValueIsNull(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);

        self::assertNull($this->converter()->read($attribute, []));
        self::assertNull($this->converter()->read($attribute, [$this->row($attribute, 'width', [])]));
    }

    public function testReadUnsupportedFieldTypeThrows(): void
    {
        $attribute = $this->attribute(self::UNSUPPORTED);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The form field type "checkbox" of attribute type "unsupported_attribute" has no value mapping.');

        $this->converter()->read($attribute, [$this->row($attribute, 'value', ['number' => 1.0])]);
    }

    public function testReadAllGroupsTheRowsByAttribute(): void
    {
        $number = $this->attribute(AttributeInterface::TYPE_NUMBER, 'number-uuid');
        $dimensions = $this->attribute(self::DIMENSIONS, 'dimensions-uuid');
        $multiSelect = $this->attribute(self::MULTI_SELECT, 'multi-select-uuid');

        $rows = (function() use ($number, $dimensions, $multiSelect): \Generator {
            yield $this->row($dimensions, 'width', ['number' => 10.0]);
            yield $this->row($number, 'value', ['number' => 3.0]);
            yield $this->row($multiSelect, 'red', ['option' => 'red']);
            yield $this->row($dimensions, 'height', ['number' => 20.0]);
            yield $this->row($multiSelect, 'green', ['option' => 'green']);
        })();

        $values = $this->converter()->readAll($rows);

        self::assertSame(['dimensions-uuid', 'number-uuid', 'multi-select-uuid'], \array_keys($values));
        self::assertSame($dimensions, $values['dimensions-uuid']['attribute']);
        self::assertSame(['width' => 10.0, 'height' => 20.0], $values['dimensions-uuid']['value']);
        self::assertSame($number, $values['number-uuid']['attribute']);
        self::assertSame(3.0, $values['number-uuid']['value']);
        self::assertSame($multiSelect, $values['multi-select-uuid']['attribute']);
        self::assertSame(['red', 'green'], $values['multi-select-uuid']['value']);
    }

    public function testReadAllWithoutRowsIsEmpty(): void
    {
        self::assertSame([], $this->converter()->readAll([]));
    }

    /**
     * @return iterable<string, array{string, mixed, array<string, array{number: float|null, text: string|null, option: string|null}>}>
     */
    public static function provideSingleValueWrites(): iterable
    {
        $timestamp = (float) (new \DateTimeImmutable('2024-02-29', new \DateTimeZone('UTC')))->getTimestamp();

        yield 'number float' => [AttributeInterface::TYPE_NUMBER, 42.5, ['value' => self::columns(number: 42.5)]];
        yield 'number int' => [AttributeInterface::TYPE_NUMBER, 3, ['value' => self::columns(number: 3.0)]];
        yield 'number zero is a value' => [AttributeInterface::TYPE_NUMBER, 0, ['value' => self::columns(number: 0.0)]];
        yield 'number numeric string' => [AttributeInterface::TYPE_NUMBER, '42.5', ['value' => self::columns(number: 42.5)]];
        yield 'date' => [AttributeInterface::TYPE_DATE, '2024-02-29', ['value' => self::columns(number: $timestamp)]];
        yield 'text' => [AttributeInterface::TYPE_TEXT, 'hello', ['value' => self::columns(text: 'hello')]];
        yield 'text zero is a value' => [AttributeInterface::TYPE_TEXT, '0', ['value' => self::columns(text: '0')]];
        yield 'option' => [AttributeInterface::TYPE_OPTIONS, 'green', ['value' => self::columns(option: 'green')]];
        yield 'boolean string true' => [AttributeInterface::TYPE_BOOLEAN, 'true', ['value' => self::columns(number: 1.0)]];
        yield 'boolean string false' => [AttributeInterface::TYPE_BOOLEAN, 'false', ['value' => self::columns(number: 0.0)]];
        yield 'boolean true' => [AttributeInterface::TYPE_BOOLEAN, true, ['value' => self::columns(number: 1.0)]];
        yield 'boolean false is a value' => [AttributeInterface::TYPE_BOOLEAN, false, ['value' => self::columns(number: 0.0)]];
    }

    /**
     * @param array<string, array{number: float|null, text: string|null, option: string|null}> $expected
     */
    #[DataProvider('provideSingleValueWrites')]
    public function testWriteSingleField(string $type, mixed $raw, array $expected): void
    {
        self::assertSame($expected, $this->toColumns($this->converter()->write($this->attribute($type), $raw)));
    }

    public function testWriteSingleFieldUsesTheFieldName(): void
    {
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_amount', 'en', [])->willReturn($this->fragment(['amount' => 'number']));

        $rows = (new AttributeValueConverter($loader->reveal()))->write($this->attribute('amount'), 5);

        self::assertSame(['amount' => self::columns(number: 5.0)], $this->toColumns($rows));
    }

    public function testWriteOptionReturnsTheOptionOfTheAttribute(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_OPTIONS);

        $rows = $this->converter()->write($attribute, 'red');

        self::assertSame($attribute->getOption('red'), $rows['value']['option'] ?? null);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function provideEmptyWrites(): iterable
    {
        foreach ([
            AttributeInterface::TYPE_NUMBER,
            AttributeInterface::TYPE_DATE,
            AttributeInterface::TYPE_TEXT,
            AttributeInterface::TYPE_OPTIONS,
            AttributeInterface::TYPE_BOOLEAN,
            self::MULTI_SELECT,
            self::DIMENSIONS,
        ] as $type) {
            yield $type . ' null' => [$type, null];
            yield $type . ' empty string' => [$type, ''];
        }

        yield 'multi select empty list' => [self::MULTI_SELECT, []];
        yield 'object empty array' => [self::DIMENSIONS, []];
    }

    #[DataProvider('provideEmptyWrites')]
    public function testWriteEmptyValueHasNoRows(string $type, mixed $raw): void
    {
        self::assertSame([], $this->converter()->write($this->attribute($type), $raw));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function provideInvalidWrites(): iterable
    {
        yield 'number not numeric' => [AttributeInterface::TYPE_NUMBER, 'abc'];
        yield 'number bool' => [AttributeInterface::TYPE_NUMBER, true];
        yield 'number array' => [AttributeInterface::TYPE_NUMBER, [1]];
        yield 'date wrong format' => [AttributeInterface::TYPE_DATE, '29.02.2024'];
        yield 'date not a date' => [AttributeInterface::TYPE_DATE, 'not-a-date'];
        yield 'date without leading zeros' => [AttributeInterface::TYPE_DATE, '2024-2-9'];
        yield 'date impossible' => [AttributeInterface::TYPE_DATE, '2024-02-30'];
        yield 'date not a leap day' => [AttributeInterface::TYPE_DATE, '2023-02-29'];
        yield 'date with time' => [AttributeInterface::TYPE_DATE, '2024-02-29 12:00:00'];
        yield 'date not a string' => [AttributeInterface::TYPE_DATE, 1709164800];
        yield 'text int' => [AttributeInterface::TYPE_TEXT, 123];
        yield 'text bool' => [AttributeInterface::TYPE_TEXT, true];
        yield 'text array' => [AttributeInterface::TYPE_TEXT, ['a']];
        yield 'option unknown' => [AttributeInterface::TYPE_OPTIONS, 'blue'];
        yield 'option not a string' => [AttributeInterface::TYPE_OPTIONS, 1];
        yield 'boolean other string' => [AttributeInterface::TYPE_BOOLEAN, 'yes'];
        yield 'boolean uppercase string' => [AttributeInterface::TYPE_BOOLEAN, 'TRUE'];
        yield 'boolean int' => [AttributeInterface::TYPE_BOOLEAN, 1];
        yield 'boolean zero' => [AttributeInterface::TYPE_BOOLEAN, 0];
        yield 'boolean array' => [AttributeInterface::TYPE_BOOLEAN, ['true']];
        yield 'multi select string' => [self::MULTI_SELECT, 'red'];
        yield 'multi select map' => [self::MULTI_SELECT, ['a' => 'red']];
        yield 'multi select unknown option' => [self::MULTI_SELECT, ['red', 'blue']];
        yield 'multi select not strings' => [self::MULTI_SELECT, [1]];
        yield 'multi select nested list' => [self::MULTI_SELECT, [['red']]];
        yield 'object not an array' => [self::DIMENSIONS, 'abc'];
        yield 'object number' => [self::DIMENSIONS, 5];
        yield 'object unknown field' => [self::DIMENSIONS, ['width' => 1, 'depth' => 2]];
        yield 'object invalid field' => [self::DIMENSIONS, ['width' => 1, 'height' => 'abc']];
        yield 'unsupported field type' => [self::UNSUPPORTED, 'on'];
    }

    #[DataProvider('provideInvalidWrites')]
    public function testWriteInvalidValueThrows(string $type, mixed $raw): void
    {
        $this->expectException(
            self::UNSUPPORTED === $type ? \LogicException::class : \InvalidArgumentException::class,
        );

        $this->converter()->write($this->attribute($type), $raw);
    }

    public function testWriteUnknownOptionNamesAttributeAndOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute "attribute" has no option "blue".');

        $this->converter()->write($this->attribute(AttributeInterface::TYPE_OPTIONS), 'blue');
    }

    public function testWriteInvalidDateNamesTheFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a valid date in format "Y-m-d", got "2024-02-30".');

        $this->converter()->write($this->attribute(AttributeInterface::TYPE_DATE), '2024-02-30');
    }

    public function testWriteMultiSelectCreatesOneRowPerOptionNamedByItsUuid(): void
    {
        $attribute = $this->attribute(self::MULTI_SELECT);
        $green = $attribute->getOption('green');
        $red = $attribute->getOption('red');
        self::assertNotNull($green);
        self::assertNotNull($red);

        $rows = $this->converter()->write($attribute, ['green', 'red']);

        self::assertSame([$green->getUuid(), $red->getUuid()], \array_keys($rows));
        self::assertSame($green, $rows[$green->getUuid()]['option'] ?? null);
        self::assertSame($red, $rows[$red->getUuid()]['option'] ?? null);
        self::assertArrayNotHasKey('number', $rows[$green->getUuid()]);
        self::assertArrayNotHasKey('text', $rows[$green->getUuid()]);
    }

    public function testWriteMultiSelectCollapsesDuplicatesOntoOneRow(): void
    {
        $attribute = $this->attribute(self::MULTI_SELECT);
        $red = $attribute->getOption('red');
        $green = $attribute->getOption('green');
        self::assertNotNull($red);
        self::assertNotNull($green);

        $rows = $this->converter()->write($attribute, ['red', 'green', 'red']);

        self::assertSame([$red->getUuid(), $green->getUuid()], \array_keys($rows));
    }

    public function testWriteObjectCreatesARowPerGivenField(): void
    {
        $rows = $this->converter()->write($this->attribute(self::DIMENSIONS), ['width' => 10, 'height' => '20.5']);

        self::assertSame(
            ['width' => self::columns(number: 10.0), 'height' => self::columns(number: 20.5)],
            $this->toColumns($rows),
        );
    }

    public function testWritePartialObjectOnlyCreatesTheGivenRows(): void
    {
        $rows = $this->converter()->write($this->attribute(self::DIMENSIONS), ['height' => 20.5, 'width' => null]);

        self::assertSame(['height' => self::columns(number: 20.5)], $this->toColumns($rows));
    }

    public function testWriteObjectWithUnknownFieldNamesIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute "attribute" has no field "depth", "length".');

        $this->converter()->write($this->attribute(self::DIMENSIONS), ['width' => 1, 'depth' => 2, 'length' => 3]);
    }

    public function testWriteObjectNeedsAnArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an object for attribute "attribute".');

        $this->converter()->write($this->attribute(self::DIMENSIONS), 'abc');
    }

    public function testWriteWithoutFragmentThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute type "no_fragment_attribute" has no form fragment.');

        $this->converter()->write($this->attribute(self::NO_FRAGMENT), 'value');
    }

    public function testWrittenRowsReadBackTheSameValue(): void
    {
        $attribute = $this->attribute(self::DIMENSIONS);
        $converter = $this->converter();

        $rows = [];
        foreach ($converter->write($attribute, ['width' => 10, 'height' => 20.5]) as $valueKey => $data) {
            $rows[] = $this->row($attribute, $valueKey, [
                'number' => $data['number'] ?? null,
                'text' => $data['text'] ?? null,
                'option' => isset($data['option']) ? $data['option']->getKey() : null,
            ]);
        }

        self::assertSame(['width' => 10.0, 'height' => 20.5], $converter->read($attribute, $rows));
    }

    /**
     * A converter whose loader knows the fragments of {@see self::FRAGMENTS}, and none for any other type.
     */
    private function converter(): AttributeValueConverter
    {
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata(Argument::any(), 'en', [])->willReturn(null);
        foreach (self::FRAGMENTS as $type => $fields) {
            $loader->getMetadata('product_attribute_' . $type, 'en', [])->willReturn(self::fragment($fields));
        }

        return new AttributeValueConverter($loader->reveal());
    }

    /**
     * @param array<string, string> $fields field type by field name
     */
    private static function fragment(array $fields): FormMetadata
    {
        $fragment = new FormMetadata();
        foreach ($fields as $name => $type) {
            $fragment->addItem(self::field($name, $type));
        }

        return $fragment;
    }

    private static function field(string $name, string $type): FieldMetadata
    {
        $field = new FieldMetadata($name);
        $field->setType($type);

        return $field;
    }

    /**
     * An attribute of the given type with the options "red" and "green".
     */
    private function attribute(string $type, ?string $uuid = null): Attribute
    {
        $attribute = new Attribute(new AttributeGroup(), $uuid);
        $attribute->setKey('attribute');
        $attribute->setType($type);
        $attribute->addOption(new AttributeOption($attribute, 'red'));
        $attribute->addOption(new AttributeOption($attribute, 'green'));

        return $attribute;
    }

    /**
     * @param array{number?: float|null, text?: string|null, option?: string|null} $columns
     */
    private function row(Attribute $attribute, string $valueKey, array $columns): ProductAttributeValue
    {
        $optionKey = $columns['option'] ?? null;

        $row = new ProductAttributeValue(
            new ProductDimensionContent(new Product()),
            $attribute,
            $attribute->getKey(),
            null !== $optionKey ? $attribute->getOption($optionKey) : null,
            $valueKey,
        );
        $row->setNumber($columns['number'] ?? null);
        $row->setText($columns['text'] ?? null);

        return $row;
    }

    /**
     * @return array{number: float|null, text: string|null, option: string|null}
     */
    private static function columns(?float $number = null, ?string $text = null, ?string $option = null): array
    {
        return ['number' => $number, 'text' => $text, 'option' => $option];
    }

    public function testAProjectConverterHandlesAFieldTypeOfItsOwn(): void
    {
        $loader = $this->prophesize(FormMetadataLoaderInterface::class);
        $loader->getMetadata('product_attribute_shade', 'en', [])->willReturn(self::fragment(['value' => 'color_picker']));

        $converter = new class($loader->reveal()) extends AttributeValueConverter {
            protected function readField(AttributeInterface $attribute, string $name, string $type, array $rows): mixed
            {
                return 'color_picker' === $type ? \strtoupper((string) ($rows[$name] ?? null)?->getText()) : parent::readField($attribute, $name, $type, $rows);
            }

            protected function writeField(AttributeInterface $attribute, string $name, string $type, mixed $raw): array
            {
                return 'color_picker' === $type ? [$name => ['text' => \ltrim(\is_string($raw) ? $raw : '', '#')]] : parent::writeField($attribute, $name, $type, $raw);
            }
        };
        $attribute = $this->attribute('shade');

        self::assertSame(['value' => ['text' => 'ff0000']], $converter->write($attribute, '#ff0000'));
        self::assertSame('FF0000', $converter->read($attribute, [$this->row($attribute, 'value', ['text' => 'ff0000'])]));
    }

    /**
     * @param array<string, array{number?: float, text?: string, option?: AttributeOptionInterface}> $rows
     *
     * @return array<string, array{number: float|null, text: string|null, option: string|null}>
     */
    private function toColumns(array $rows): array
    {
        return \array_map(
            static fn (array $data): array => self::columns($data['number'] ?? null, $data['text'] ?? null, ($data['option'] ?? null)?->getKey()),
            $rows,
        );
    }
}
