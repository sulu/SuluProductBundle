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

namespace Sulu\Product\Tests\Unit\Domain\Measurement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Measurement\MeasurementFamily;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Measurement\Unit;

#[CoversClass(MeasurementRegistry::class)]
#[CoversClass(MeasurementFamily::class)]
#[CoversClass(Unit::class)]
class MeasurementRegistryTest extends TestCase
{
    /**
     * @param MeasurementFamily[] $families
     *
     * @return string[]
     */
    private function familyKeys(array $families): array
    {
        return \array_map(static fn (MeasurementFamily $family): string => $family->getKey(), $families);
    }

    /**
     * @param Unit[] $units
     *
     * @return array<string, string> unit key => symbol
     */
    private function unitMap(array $units): array
    {
        $map = [];
        foreach ($units as $unit) {
            $map[$unit->getKey()] = $unit->getSymbol();
        }

        return $map;
    }

    public function testGetFamiliesWithoutConfigContainsAllBuiltInFamilies(): void
    {
        $keys = $this->familyKeys((new MeasurementRegistry())->getFamilies());

        foreach (['area', 'binary', 'decibel', 'duration', 'electric_charge', 'energy',
            'frequency', 'intensity', 'length', 'power', 'pressure', 'resistance',
            'speed', 'temperature', 'voltage', 'volume', 'weight'] as $key) {
            $this->assertContains($key, $keys, "Missing family: $key");
        }
    }

    public function testNullAndEmptyMapBehaveIdentically(): void
    {
        $this->assertSame(
            $this->familyKeys((new MeasurementRegistry(null))->getFamilies()),
            $this->familyKeys((new MeasurementRegistry([]))->getFamilies()),
        );
    }

    public function testGetUnitsWithoutConfigReturnsUnitObjectsForFamily(): void
    {
        $units = $this->unitMap((new MeasurementRegistry())->getUnits('length'));

        $this->assertSame('cm', $units['CENTIMETER']);
        $this->assertSame('m', $units['METER']);
        $this->assertSame('km', $units['KILOMETER']);
    }

    public function testGetUnitsAcceptsMeasurementFamilyObject(): void
    {
        $registry = new MeasurementRegistry();
        $units = $this->unitMap($registry->getUnits(new MeasurementFamily('weight')));

        $this->assertSame('kg', $units['KILOGRAM']);
    }

    public function testUnitKnowsItsFamily(): void
    {
        $units = (new MeasurementRegistry())->getUnits('length');

        $this->assertSame('length', $units[0]->getMeasurementFamily()->getKey());
    }

    public function testGetUnitsForUnknownFamilyReturnsEmpty(): void
    {
        $this->assertSame([], (new MeasurementRegistry())->getUnits('unknown_family'));
    }

    public function testGetUnitsForEmptyStringReturnsEmpty(): void
    {
        $this->assertSame([], (new MeasurementRegistry())->getUnits(''));
    }

    public function testWhitelistRestrictsToListedFamilies(): void
    {
        $registry = new MeasurementRegistry(['length' => [], 'binary' => ['BYTE', 'KILOBYTE']]);

        $this->assertSame(['binary', 'length'], $this->familyKeys($registry->getFamilies()));
    }

    public function testFamilyWithEmptyUnitListEnablesAllUnits(): void
    {
        $registry = new MeasurementRegistry(['length' => []]);

        $all = $this->unitMap((new MeasurementRegistry())->getUnits('length'));
        $enabled = $this->unitMap($registry->getUnits('length'));

        $this->assertSame($all, $enabled);
    }

    public function testFamilyWithUnitSubsetEnablesOnlyListedUnits(): void
    {
        $registry = new MeasurementRegistry(['binary' => ['BYTE', 'KILOBYTE']]);

        $this->assertSame(['BYTE', 'KILOBYTE'], \array_keys($this->unitMap($registry->getUnits('binary'))));
    }

    public function testDisabledFamilyReturnsNoUnits(): void
    {
        $registry = new MeasurementRegistry(['length' => []]);

        $this->assertSame([], $registry->getUnits('weight'));
    }

    public function testFindUnitResolvesEnabledUnitToItsFamily(): void
    {
        $unit = (new MeasurementRegistry())->findUnit('METER');

        $this->assertInstanceOf(Unit::class, $unit);
        $this->assertSame('METER', $unit->getKey());
        $this->assertSame('m', $unit->getSymbol());
        $this->assertSame('length', $unit->getMeasurementFamily()->getKey());
    }

    public function testFindUnitReturnsNullForUnknownKey(): void
    {
        $this->assertNull((new MeasurementRegistry())->findUnit('NON_EXISTENT_UNIT'));
    }

    public function testFindUnitReturnsNullForDisabledUnit(): void
    {
        $registry = new MeasurementRegistry(['binary' => ['BYTE']]);

        $this->assertNull($registry->findUnit('KILOBYTE'));
    }

    public function testFindUnitReturnsNullWhenFamilyDisabled(): void
    {
        $registry = new MeasurementRegistry(['binary' => []]);

        $this->assertNull($registry->findUnit('METER'));
    }

    public function testFindUnitResolvesFirstMatchInCatalogOrderForAmbiguousKey(): void
    {
        $unit = (new MeasurementRegistry())->findUnit('OUNCE');

        $this->assertInstanceOf(Unit::class, $unit);
        $this->assertSame('volume', $unit->getMeasurementFamily()->getKey());
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
            'energy KILOWATT_HOUR' => ['energy', 'KILOWATT_HOUR', 'kWh'],
            'frequency MEGAHERTZ' => ['frequency', 'MEGAHERTZ', 'MHz'],
            'intensity AMPERE' => ['intensity', 'AMPERE', 'A'],
            'power KILOWATT' => ['power', 'KILOWATT', 'kW'],
            'pressure BAR' => ['pressure', 'BAR', 'bar'],
            'resistance OHM' => ['resistance', 'OHM', 'Ω'],
            'speed KILOMETER_PER_HOUR' => ['speed', 'KILOMETER_PER_HOUR', 'km/h'],
            'temperature CELSIUS' => ['temperature', 'CELSIUS', '°C'],
            'voltage VOLT' => ['voltage', 'VOLT', 'V'],
            'volume LITER' => ['volume', 'LITER', 'L'],
            'weight KILOGRAM' => ['weight', 'KILOGRAM', 'kg'],
        ];
    }

    #[DataProvider('familySpotCheckProvider')]
    public function testFamilySpotCheck(string $family, string $unitKey, string $expectedSymbol): void
    {
        $units = $this->unitMap((new MeasurementRegistry())->getUnits($family));

        $this->assertArrayHasKey($unitKey, $units);
        $this->assertSame($expectedSymbol, $units[$unitKey]);
    }
}
