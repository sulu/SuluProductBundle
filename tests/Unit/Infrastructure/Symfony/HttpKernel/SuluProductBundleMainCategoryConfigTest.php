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
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class SuluProductBundleMainCategoryConfigTest extends TestCase
{
    public function testEveryCategoryCanBeChosenByDefault(): void
    {
        self::assertNull($this->processRootKey([]));
    }

    public function testTheRootKeyIsConfigurable(): void
    {
        self::assertSame('shop', $this->processRootKey(['main_category' => ['root_key' => 'shop']]));
    }

    /**
     * @param array<string, mixed> $projectConfig
     */
    private function processRootKey(array $projectConfig): ?string
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', \sys_get_temp_dir());

        $extension = (new SuluProductBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $configs = $builder->getExtensionConfig('sulu_product');
        $configs[] = $projectConfig;

        $configuration = $extension->getConfiguration([], $builder);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array{main_category: array{root_key: string|null}} $processed */
        $processed = (new Processor())->processConfiguration($configuration, $configs);

        return $processed['main_category']['root_key'];
    }
}
