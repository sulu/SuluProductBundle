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
use Sulu\Product\Application\Ai\GetProducts;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[CoversClass(SuluProductBundle::class)]
class SuluProductBundleAiToolsTest extends TestCase
{
    private const TOOLS = [
        'sulu_product.ai_get_products' => 'sulu_product_get_products',
        'sulu_product.ai_get_attributes' => 'sulu_product_get_attributes',
        'sulu_product.ai_get_attribute_values' => 'sulu_product_get_attribute_values',
        'sulu_product.ai_search_products_by_attributes' => 'sulu_product_search_products_by_attributes',
        'sulu_product.ai_get_product_details' => 'sulu_product_get_product_details',
        'sulu_product.ai_get_related_products' => 'sulu_product_get_related_products',
    ];

    public function testToolsAreRegisteredAndTaggedWhenAiAgentIsInstalled(): void
    {
        $builder = $this->loadBundle();

        foreach (self::TOOLS as $serviceId => $toolName) {
            $this->assertTrue($builder->hasDefinition($serviceId), $serviceId);

            $tags = $builder->getDefinition($serviceId)->getTag('ai.tool');
            $this->assertCount(1, $tags, $serviceId);
            $this->assertSame([$toolName], \array_column($tags, 'name'));
            $this->assertSame(['__invoke'], \array_column($tags, 'method'));
            $this->assertNotEmpty(\array_column($tags, 'description')[0]);
        }

        $this->assertSame(GetProducts::class, $builder->getDefinition('sulu_product.ai_get_products')->getClass());
    }

    private function loadBundle(): ContainerBuilder
    {
        $bundle = new SuluProductBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.bundles', []);
        $configuration = $extension->getConfiguration([], $builder);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        /** @var array<string, mixed> $config */
        $config = (new Processor())->processConfiguration($configuration, ['sulu_product' => []]);

        $file = (new \ReflectionClass(SuluProductBundle::class))->getFileName();
        self::assertIsString($file);
        $instanceof = [];
        $configurator = new ContainerConfigurator($builder, new PhpFileLoader($builder, new FileLocator(\dirname($file))), $instanceof, $file, $file);
        $bundle->loadExtension($config, $configurator, $builder);

        return $builder;
    }
}
