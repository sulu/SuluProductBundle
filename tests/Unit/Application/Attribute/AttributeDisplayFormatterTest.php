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
use Sulu\Product\Application\Attribute\AttributeDisplayFormatter;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;

#[CoversClass(AttributeDisplayFormatter::class)]
class AttributeDisplayFormatterTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function attribute(array $config = []): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig($config);

        return $attribute;
    }

    private function formatter(): AttributeDisplayFormatter
    {
        return new AttributeDisplayFormatter(new MeasurementRegistry());
    }

    public function testPlacesTheValueAndTheUnitSymbol(): void
    {
        $attribute = $this->attribute(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);

        self::assertSame('16 mm', $this->formatter()->format($attribute, 16.0));
        self::assertSame('16 mm', $this->formatter()->format($attribute, 16));
        self::assertSame('XL mm', $this->formatter()->format($attribute, 'XL'));
    }

    public function testWithoutADisplayFormatTheValueIsReturnedAsText(): void
    {
        self::assertSame('16.5', $this->formatter()->format($this->attribute(), 16.5));
        self::assertSame('16.5', $this->formatter()->format($this->attribute(['displayFormat' => '']), 16.5));
        self::assertSame('16.5', $this->formatter()->format($this->attribute(['displayFormat' => 5]), 16.5));
    }

    public function testAnUnknownOrMissingUnitRendersEmptyAndIsTrimmed(): void
    {
        self::assertSame('16', $this->formatter()->format($this->attribute(['displayFormat' => '%value% %unit%', 'unit' => 'NOPE']), 16));
        self::assertSame('16', $this->formatter()->format($this->attribute(['displayFormat' => '%value% %unit%']), 16));
    }

    public function testPlacesThePlaceholdersOfTheType(): void
    {
        $attribute = $this->attribute(['displayFormat' => '%from% to %to% %unit% (%value%)', 'unit' => 'MILLIMETER']);

        self::assertSame('1 to 2 mm (1 – 2)', $this->formatter()->format($attribute, '1 – 2', ['%from%' => '1', '%to%' => '2']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'an empty string' => [''];
        yield 'a bool' => [true];
        yield 'an array' => [['a']];
    }

    #[DataProvider('emptyValueProvider')]
    public function testAnEmptyValueFormatsToNullEvenWithADisplayFormat(mixed $value): void
    {
        self::assertNull($this->formatter()->format($this->attribute(['displayFormat' => 'Size: %value%']), $value));
    }
}
