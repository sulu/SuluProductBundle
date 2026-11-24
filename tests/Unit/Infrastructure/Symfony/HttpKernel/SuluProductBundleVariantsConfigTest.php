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

class SuluProductBundleVariantsConfigTest extends TestCase
{
    private const DEFAULTS = [
        'title' => 'product.title',
        'url' => 'product.url',
        'code' => 'product.code',
        'status' => 'product.status',
        'position' => 'product.position',
    ];

    public function testTheBundleConfigProvidesTheDefaults(): void
    {
        self::assertSame(self::DEFAULTS, $this->processVariantProperties([]));
    }

    public function testProjectPropertiesAreMergedIntoTheDefaults(): void
    {
        self::assertSame(
            [...self::DEFAULTS, 'code' => 'product.externalIdentifier', 'image' => 'product.image'],
            $this->processVariantProperties(['code' => 'product.externalIdentifier', 'image' => 'product.image']),
        );
    }

    /**
     * Runs the bundle's prepend and the project config through the configuration, as the kernel does.
     *
     * @param array<string, string> $projectProperties
     *
     * @return array<string, string>
     */
    private function processVariantProperties(array $projectProperties): array
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', \sys_get_temp_dir());

        $extension = (new SuluProductBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $configs = $builder->getExtensionConfig('sulu_product');
        $configs[] = ['variants' => ['properties' => $projectProperties]];

        $configuration = $extension->getConfiguration([], $builder);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array{variants: array{properties: array<string, string>}} $processed */
        $processed = (new Processor())->processConfiguration($configuration, $configs);

        return $processed['variants']['properties'];
    }
}
