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
    public function testAdditionalProductFiltersAreDisabledByDefault(): void
    {
        $this->assertSame(['website' => ['additional_product_filters' => false]], $this->processConfig([])['search']);
    }

    public function testAdditionalProductFiltersCanBeEnabled(): void
    {
        $processed = $this->processConfig(['search' => ['website' => ['additional_product_filters' => true]]]);

        $this->assertSame(['website' => ['additional_product_filters' => true]], $processed['search']);
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
}
