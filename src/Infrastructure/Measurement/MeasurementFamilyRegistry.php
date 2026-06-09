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

namespace Sulu\Product\Infrastructure\Measurement;

final class MeasurementFamilyRegistry
{
    /** @var array<string, array<string, string>> */
    private const DATA = [
        'area' => [
            'SQUARE_MILLIMETER' => 'mm²',
            'SQUARE_CENTIMETER' => 'cm²',
            'SQUARE_DECIMETER' => 'dm²',
            'SQUARE_METER' => 'm²',
            'HECTARE' => 'ha',
            'SQUARE_KILOMETER' => 'km²',
            'SQUARE_INCH' => 'in²',
            'SQUARE_FOOT' => 'ft²',
            'SQUARE_YARD' => 'yd²',
            'ACRE' => 'ac',
            'SQUARE_MILE' => 'mi²',
        ],
        'binary' => [
            'BIT' => 'b',
            'BYTE' => 'B',
            'KILOBYTE' => 'kB',
            'MEGABYTE' => 'MB',
            'GIGABYTE' => 'GB',
            'TERABYTE' => 'TB',
        ],
        'decibel' => [
            'DECIBEL' => 'dB',
        ],
        'duration' => [
            'MILLISECOND' => 'ms',
            'SECOND' => 's',
            'MINUTE' => 'min',
            'HOUR' => 'h',
            'DAY' => 'd',
        ],
        'electric_charge' => [
            'MILLIAMPEREHOUR' => 'mAh',
            'AMPEREHOUR' => 'Ah',
            'MILLICOULOMB' => 'mC',
            'COULOMB' => 'C',
        ],
        'energy' => [
            'JOULE' => 'J',
            'KILOJOULE' => 'kJ',
            'WATT_HOUR' => 'Wh',
            'KILOWATT_HOUR' => 'kWh',
            'CALORIE' => 'cal',
            'KILOCALORIE' => 'kcal',
        ],
        'frequency' => [
            'HERTZ' => 'Hz',
            'KILOHERTZ' => 'kHz',
            'MEGAHERTZ' => 'MHz',
            'GIGAHERTZ' => 'GHz',
            'TERAHERTZ' => 'THz',
        ],
        'intensity' => [
            'MILLIAMPERE' => 'mA',
            'CENTIAMPERE' => 'cA',
            'DECIAMPERE' => 'dA',
            'AMPERE' => 'A',
            'KILOAMPERE' => 'kA',
        ],
        'length' => [
            'MILLIMETER' => 'mm',
            'CENTIMETER' => 'cm',
            'DECIMETER' => 'dm',
            'METER' => 'm',
            'KILOMETER' => 'km',
            'INCH' => 'in',
            'FOOT' => 'ft',
            'YARD' => 'yd',
            'MILE' => 'mi',
        ],
        'power' => [
            'WATT' => 'W',
            'KILOWATT' => 'kW',
            'MEGAWATT' => 'MW',
            'GIGAWATT' => 'GW',
            'TERAWATT' => 'TW',
        ],
        'pressure' => [
            'PASCAL' => 'Pa',
            'HECTOPASCAL' => 'hPa',
            'BAR' => 'bar',
            'MILLIBAR' => 'mbar',
            'ATM' => 'atm',
            'PSI' => 'PSI',
            'TORR' => 'Torr',
            'MMHG' => 'mmHg',
        ],
        'resistance' => [
            'MILLIOHM' => 'mΩ',
            'OHM' => 'Ω',
            'KILOHM' => 'kΩ',
            'MEGOHM' => 'MΩ',
        ],
        'speed' => [
            'METER_PER_SECOND' => 'm/s',
            'KILOMETER_PER_HOUR' => 'km/h',
            'MILE_PER_HOUR' => 'mi/h',
        ],
        'temperature' => [
            'CELSIUS' => '°C',
            'FAHRENHEIT' => '°F',
            'KELVIN' => 'K',
        ],
        'voltage' => [
            'MILLIVOLT' => 'mV',
            'VOLT' => 'V',
            'KILOVOLT' => 'kV',
        ],
        'volume' => [
            'CUBIC_MILLIMETER' => 'mm³',
            'CUBIC_CENTIMETER' => 'cm³',
            'MILLILITER' => 'mL',
            'CENTILITER' => 'cL',
            'DECILITER' => 'dL',
            'LITER' => 'L',
            'CUBIC_METER' => 'm³',
            'OUNCE' => 'oz',
            'PINT' => 'pt',
            'GALLON' => 'gal',
            'CUBIC_FOOT' => 'ft³',
        ],
        'weight' => [
            'MILLIGRAM' => 'mg',
            'GRAM' => 'g',
            'KILOGRAM' => 'kg',
            'TON' => 't',
            'OUNCE' => 'oz',
            'POUND' => 'lb',
        ],
    ];

    /** @return string[] */
    public function getFamilies(): array
    {
        return \array_keys(self::DATA);
    }

    /** @return array<string, string> */
    public function getUnits(string $family): array
    {
        return self::DATA[$family] ?? [];
    }
}
