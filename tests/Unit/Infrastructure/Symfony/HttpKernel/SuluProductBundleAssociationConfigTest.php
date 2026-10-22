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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

#[CoversClass(SuluProductBundle::class)]
final class SuluProductBundleAssociationConfigTest extends TestCase
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
     * @param array<string, array{label?: string|null}> $associationTypes
     *
     * @return array<string, array{label: string}>
     */
    private function resolveAssociationTypesMap(array $associationTypes): array
    {
        $bundle = new SuluProductBundle();
        $method = new \ReflectionMethod($bundle, 'resolveAssociationTypesMap');
        $method->setAccessible(true);

        /** @var array<string, array{label: string}> $result */
        $result = $method->invoke($bundle, $associationTypes);

        return $result;
    }

    public function testTerseAssociationTypeNormalizesToNullLabel(): void
    {
        $processed = $this->processConfig(['association_types' => ['alternative' => null]]);

        self::assertSame(['alternative' => ['label' => null]], $processed['association_types']);
    }

    public function testAssociationTypeWithLabelIsPreserved(): void
    {
        $processed = $this->processConfig(['association_types' => ['suitable' => ['label' => 'custom.label']]]);

        self::assertSame(['suitable' => ['label' => 'custom.label']], $processed['association_types']);
    }

    public function testDigitOnlyAssociationTypeKeyIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/association type key/');

        $this->processConfig(['association_types' => ['123' => null]]);
    }

    public function testAssociationTypeKeyWithSlashIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/association type key/');

        $this->processConfig(['association_types' => ['foo/bar' => null]]);
    }

    public function testOmittedAssociationTypesNormalizesToEmptyArray(): void
    {
        $processed = $this->processConfig([]);

        self::assertSame([], $processed['association_types']);
    }

    public function testResolveAssociationTypesMapFillsConventionLabelAndKeepsOverride(): void
    {
        $resolvedMap = $this->resolveAssociationTypesMap([
            'alternative' => ['label' => null],
            'suitable' => ['label' => 'custom.label'],
        ]);

        self::assertSame([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
            'suitable' => ['label' => 'custom.label'],
        ], $resolvedMap);
    }

    public function testResolveAssociationTypesMapFillsConventionLabelWhenLabelKeyAbsent(): void
    {
        $resolvedMap = $this->resolveAssociationTypesMap([
            'alternative' => [],
        ]);

        self::assertSame([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
        ], $resolvedMap);
    }

    public function testEmptyAssociationTypesResolvesToEmptyArray(): void
    {
        self::assertSame([], $this->resolveAssociationTypesMap([]));
    }
}
