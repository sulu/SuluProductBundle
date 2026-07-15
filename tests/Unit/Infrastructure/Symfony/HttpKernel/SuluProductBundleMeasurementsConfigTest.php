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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\HttpKernel;

use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

class SuluProductBundleMeasurementsConfigTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function processConfig(array $config): array
    {
        $bundle = new SuluProductBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration($configuration, ['sulu_product' => $config]);

        return $processed;
    }

    /**
     * @param array<string, array{units?: array<string>}> $measurements
     *
     * @return array<string, list<string>>|null
     */
    private function resolveEnabledMap(array $measurements): ?array
    {
        $bundle = new SuluProductBundle();
        $method = new \ReflectionMethod($bundle, 'resolveMeasurementsEnabledMap');
        $method->setAccessible(true);

        /** @var array<string, list<string>>|null $result */
        $result = $method->invoke($bundle, $measurements);

        return $result;
    }

    public function testTerseFamilyNormalizesToEmptyUnitList(): void
    {
        $processed = $this->processConfig(['measurements' => ['length' => null]]);

        self::assertSame(['length' => ['units' => []]], $processed['measurements']);
    }

    public function testEmptyFamilyNormalizesToEmptyUnitList(): void
    {
        $processed = $this->processConfig(['measurements' => ['length' => []]]);

        self::assertSame(['length' => ['units' => []]], $processed['measurements']);
    }

    public function testFamilyWithUnitSubsetIsPreserved(): void
    {
        $processed = $this->processConfig(['measurements' => ['binary' => ['units' => ['BYTE', 'KILOBYTE']]]]);

        self::assertSame(['binary' => ['units' => ['BYTE', 'KILOBYTE']]], $processed['measurements']);
    }

    public function testNoMeasurementsBlockResolvesToNull(): void
    {
        self::assertNull($this->resolveEnabledMap([]));
    }

    public function testResolvesNormalizedConfigToEnabledMap(): void
    {
        $enabledMap = $this->resolveEnabledMap([
            'length' => ['units' => []],
            'binary' => ['units' => ['BYTE']],
        ]);

        self::assertSame(['length' => [], 'binary' => ['BYTE']], $enabledMap);
    }

    public function testUnknownFamilyRaisesConfigException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Unknown measurement family "nonsense"/');

        $this->resolveEnabledMap(['nonsense' => ['units' => []]]);
    }

    public function testUnknownUnitRaisesConfigException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Unknown measurement unit "NONSENSE"/');

        $this->resolveEnabledMap(['binary' => ['units' => ['NONSENSE']]]);
    }
}
