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

namespace Sulu\Product\Tests\Unit\Infrastructure\Measurement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Measurement\MeasurementFamilyRegistry;

#[CoversClass(MeasurementFamilyRegistry::class)]
class MeasurementFamilyRegistryTest extends TestCase
{
    private MeasurementFamilyRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new MeasurementFamilyRegistry();
    }

    public function testGetFamiliesContainsExpectedKeys(): void
    {
        $families = $this->registry->getFamilies();
        foreach (['area', 'binary', 'decibel', 'duration', 'electric_charge', 'energy',
            'frequency', 'intensity', 'length', 'power', 'pressure', 'resistance',
            'speed', 'temperature', 'voltage', 'volume', 'weight'] as $key) {
            $this->assertContains($key, $families, "Missing family: $key");
        }
    }

    public function testGetUnitsForLength(): void
    {
        $units = $this->registry->getUnits('length');
        $this->assertArrayHasKey('CENTIMETER', $units);
        $this->assertSame('cm', $units['CENTIMETER']);
        $this->assertArrayHasKey('METER', $units);
        $this->assertSame('m', $units['METER']);
        $this->assertArrayHasKey('KILOMETER', $units);
        $this->assertSame('km', $units['KILOMETER']);
    }

    public function testGetUnitsForWeight(): void
    {
        $units = $this->registry->getUnits('weight');
        $this->assertArrayHasKey('KILOGRAM', $units);
        $this->assertSame('kg', $units['KILOGRAM']);
    }

    public function testGetUnitsForUnknownFamilyReturnsEmpty(): void
    {
        $this->assertSame([], $this->registry->getUnits('unknown_family'));
    }

    public function testGetUnitsForEmptyStringReturnsEmpty(): void
    {
        $this->assertSame([], $this->registry->getUnits(''));
    }

    public function testGetUnitsForEnergyFamily(): void
    {
        $units = $this->registry->getUnits('energy');
        $this->assertArrayHasKey('KILOWATT_HOUR', $units);
        $this->assertSame('kWh', $units['KILOWATT_HOUR']);
        $this->assertArrayHasKey('KILOCALORIE', $units);
        $this->assertSame('kcal', $units['KILOCALORIE']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function familySpotCheckProvider(): array
    {
        return [
            'area ACRE' => ['area', 'ACRE', 'ac'],
            'binary GIGABYTE' => ['binary', 'GIGABYTE', 'GB'],
            'decibel DECIBEL' => ['decibel', 'DECIBEL', 'dB'],
            'duration MINUTE' => ['duration', 'MINUTE', 'min'],
            'electric_charge COULOMB' => ['electric_charge', 'COULOMB', 'C'],
            'frequency MEGAHERTZ' => ['frequency', 'MEGAHERTZ', 'MHz'],
            'intensity AMPERE' => ['intensity', 'AMPERE', 'A'],
            'power KILOWATT' => ['power', 'KILOWATT', 'kW'],
            'pressure BAR' => ['pressure', 'BAR', 'bar'],
            'pressure MILLIBAR' => ['pressure', 'MILLIBAR', 'mbar'],
            'resistance OHM' => ['resistance', 'OHM', 'Ω'],
            'speed KILOMETER_PER_HOUR' => ['speed', 'KILOMETER_PER_HOUR', 'km/h'],
            'temperature CELSIUS' => ['temperature', 'CELSIUS', '°C'],
            'voltage VOLT' => ['voltage', 'VOLT', 'V'],
            'volume LITER' => ['volume', 'LITER', 'L'],
        ];
    }

    #[DataProvider('familySpotCheckProvider')]
    public function testFamilySpotCheck(string $family, string $unitKey, string $expectedSymbol): void
    {
        $units = $this->registry->getUnits($family);
        $this->assertArrayHasKey($unitKey, $units);
        $this->assertSame($expectedSymbol, $units[$unitKey]);
    }
}
