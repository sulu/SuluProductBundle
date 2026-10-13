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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Application\AttributeType\RangeAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Tests\Unit\Support\AttributeTypes;
use Sulu\Product\Tests\Unit\Support\FormOf;

#[CoversClass(RangeAttributeType::class)]
class RangeAttributeTypeTest extends TestCase
{
    private function type(): RangeAttributeType
    {
        return new RangeAttributeType(AttributeTypes::number(), AttributeTypes::displayFormatter());
    }

    public function testKey(): void
    {
        self::assertSame(AttributeInterface::TYPE_RANGE, $this->type()->getKey());
    }

    public function testConfigureFormAddsMinMaxStepFromConfigToEveryField(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['min' => -20, 'max' => 100, 'step' => 0.5]);

        $from = new FieldMetadata('from');
        $to = new FieldMetadata('to');
        $this->type()->configureForm(FormOf::fields($from, $to), $attribute, 'en');

        foreach ([$from, $to] as $field) {
            $options = $field->getOptions();
            self::assertSame('-20', $options['min']->getValue());
            self::assertSame('100', $options['max']->getValue());
            self::assertSame('0.5', $options['step']->getValue());
        }
    }

    public function testConfigureFormAddsNoOptionsWithoutConfig(): void
    {
        $field = new FieldMetadata('to');
        $this->type()->configureForm(FormOf::fields($field), new Attribute(new AttributeGroup()), 'en');

        self::assertSame([], $field->getOptions());
    }

    public function testConfigureFormSkipsMissingConfigKeys(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['max' => 60]);

        $field = new FieldMetadata('to');
        $this->type()->configureForm(FormOf::fields($field), $attribute, 'en');

        $options = $field->getOptions();
        self::assertArrayHasKey('max', $options);
        self::assertArrayNotHasKey('min', $options);
        self::assertArrayNotHasKey('step', $options);
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function rangeProvider(): iterable
    {
        yield 'both bounds' => [['from' => -20.0, 'to' => 60.0], '-20 – 60'];
        yield 'decimals are kept' => [['from' => 1.5, 'to' => 4.25], '1.5 – 4.25'];
        yield 'integers are shown without decimals' => [['from' => 2.0, 'to' => 10.0], '2 – 10'];
        yield 'from only' => [['from' => 5.0], '≥ 5'];
        yield 'from only with a null to' => [['from' => 5.0, 'to' => null], '≥ 5'];
        yield 'to only' => [['to' => 7.5], '≤ 7.5'];
        yield 'to only with a null from' => [['from' => null, 'to' => 7.5], '≤ 7.5'];
        yield 'zero is a bound' => [['from' => 0.0, 'to' => 0.0], '0 – 0'];
        yield 'both bounds null' => [['from' => null, 'to' => null], null];
        yield 'empty array' => [[], null];
        yield 'null' => [null, null];
        yield 'a scalar' => [5.0, null];
        yield 'a string' => [['from' => '5', 'to' => '6'], null];
    }

    #[DataProvider('rangeProvider')]
    public function testFormatValue(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, $this->type()->formatValue(new Attribute(new AttributeGroup()), $value, 'en'));
    }

    /**
     * @return iterable<string, array{string, mixed, string|null}>
     */
    public static function rangeDisplayFormatProvider(): iterable
    {
        yield '%value% is the rendered range' => ['%value% %unit%', ['from' => -20.0, 'to' => 60.0], '-20 – 60 mm'];
        yield '%value% of an open range' => ['%value% %unit%', ['from' => 5.0], '≥ 5 mm'];
        yield '%from% and %to%' => ['%from% to %to% %unit%', ['from' => 1.5, 'to' => 4.0], '1.5 to 4 mm'];
        yield 'bounds in the wrong order' => ['%to%/%from%', ['from' => 1.0, 'to' => 2.0], '2/1'];
        yield 'a missing to renders empty' => ['%from%-%to% %unit%', ['from' => 5.0], '5- mm'];
        yield 'a missing from renders empty' => ['up to %to%%unit%', ['to' => 9.0], 'up to 9mm'];
        yield 'nothing to render' => ['%from% - %to% %unit%', ['from' => null, 'to' => null], null];
    }

    #[DataProvider('rangeDisplayFormatProvider')]
    public function testFormatValueWithDisplayFormatAndUnit(string $format, mixed $value, ?string $expected): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['displayFormat' => $format, 'unit' => 'MILLIMETER']);

        self::assertSame($expected, $this->type()->formatValue($attribute, $value, 'en'));
    }

    public function testHasADisplayFormat(): void
    {
        self::assertTrue($this->type()->hasDisplayFormat());
    }
}
