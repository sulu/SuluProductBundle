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
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

class SuluProductBundleSearchConfigTest extends TestCase
{
    public function testAdditionalProductFiltersAreEnabledByDefault(): void
    {
        $this->assertSame(['website' => ['additional_product_filters' => true]], $this->processConfig([])['search']);
    }

    public function testAdditionalProductFiltersCanBeDisabled(): void
    {
        $processed = $this->processConfig(['search' => ['website' => ['additional_product_filters' => false]]]);

        $this->assertSame(['website' => ['additional_product_filters' => false]], $processed['search']);
    }

    public function testPrependReadsTheRawConfigLastOneWinning(): void
    {
        $this->assertTrue($this->isAdditionalProductFiltersEnabled([]));
        $this->assertTrue($this->isAdditionalProductFiltersEnabled([['variant_query_parameter' => 'v']]));
        $this->assertFalse($this->isAdditionalProductFiltersEnabled([
            ['search' => ['website' => ['additional_product_filters' => true]]],
            ['search' => ['website' => ['additional_product_filters' => false]]],
        ]));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function processConfig(array $config): array
    {
        $extension = (new SuluProductBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array<string, mixed> */
        return (new Processor())->processConfiguration($configuration, ['sulu_product' => $config]);
    }

    /**
     * @param array<int, array<string, mixed>> $configs in load order
     */
    private function isAdditionalProductFiltersEnabled(array $configs): bool
    {
        $builder = new ContainerBuilder();
        foreach (\array_reverse($configs) as $config) {
            $builder->prependExtensionConfig('sulu_product', $config);
        }

        $method = new \ReflectionMethod(SuluProductBundle::class, 'isAdditionalProductFiltersEnabled');

        /** @var bool */
        return $method->invoke(new SuluProductBundle(), $builder);
    }
}
