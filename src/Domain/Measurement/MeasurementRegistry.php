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

namespace Sulu\Product\Domain\Measurement;

final class MeasurementRegistry
{
    /** @var array<string, array<string, string>> */
    public const DATA = [
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

    /**
     * @var array<string, list<string>>|null
     */
    private readonly ?array $enabledMap;

    /**
     * @param array<string, list<string>>|null $enabledMap
     */
    public function __construct(?array $enabledMap = null)
    {
        $this->enabledMap = ([] === $enabledMap) ? null : $enabledMap;
    }

    /**
     * @return MeasurementFamily[] enabled families only
     */
    public function getFamilies(): array
    {
        $families = [];
        foreach (self::DATA as $familyKey => $units) {
            if ($this->isFamilyEnabled($familyKey)) {
                $families[] = new MeasurementFamily($familyKey);
            }
        }

        return $families;
    }

    /**
     * @return Unit[] enabled units of the given family
     */
    public function getUnits(MeasurementFamily|string $family): array
    {
        $familyKey = $family instanceof MeasurementFamily ? $family->getKey() : $family;

        if (!$this->isFamilyEnabled($familyKey)) {
            return [];
        }

        $familyObject = new MeasurementFamily($familyKey);

        $units = [];
        foreach (self::DATA[$familyKey] ?? [] as $unitKey => $symbol) {
            if ($this->isUnitEnabled($familyKey, $unitKey)) {
                $units[] = new Unit($unitKey, $symbol, $familyObject);
            }
        }

        return $units;
    }

    public function findUnit(string $key): ?Unit
    {
        foreach (self::DATA as $familyKey => $units) {
            if (!\array_key_exists($key, $units)) {
                continue;
            }

            if (!$this->isUnitEnabled($familyKey, $key)) {
                return null;
            }

            return new Unit($key, $units[$key], new MeasurementFamily($familyKey));
        }

        return null;
    }

    private function isFamilyEnabled(string $family): bool
    {
        if (!\array_key_exists($family, self::DATA)) {
            return false;
        }

        if (null === $this->enabledMap) {
            return true;
        }

        return \array_key_exists($family, $this->enabledMap);
    }

    private function isUnitEnabled(string $family, string $unit): bool
    {
        if (!$this->isFamilyEnabled($family)) {
            return false;
        }

        if (null === $this->enabledMap) {
            return true;
        }

        $enabledUnits = $this->enabledMap[$family];

        return [] === $enabledUnits || \in_array($unit, $enabledUnits, true);
    }
}
