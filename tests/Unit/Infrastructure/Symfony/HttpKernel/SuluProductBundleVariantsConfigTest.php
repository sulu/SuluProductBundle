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

    public function testVariantsOwnTheirRoutesByDefault(): void
    {
        self::assertSame('per_variant', $this->processVariants([])['routing']);
    }

    public function testTheProductCanOwnTheRoute(): void
    {
        self::assertSame('per_product', $this->processVariants(['routing' => 'per_product'])['routing']);
    }

    public function testTheQueryParameterIsVariantByDefault(): void
    {
        self::assertSame('variant', $this->processVariants([])['query_parameter']);
    }

    public function testTheQueryParameterCanBeRenamed(): void
    {
        self::assertSame('sku', $this->processVariants(['query_parameter' => 'sku'])['query_parameter']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidQueryParameters(): iterable
    {
        yield 'empty' => [''];
        yield 'with a space' => ['my variant'];
        yield 'with an equals sign' => ['a=b'];
        yield 'starting with a digit' => ['1variant'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideInvalidQueryParameters')]
    public function testAnInvalidQueryParameterIsRejected(string $name): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processVariants(['query_parameter' => $name]);
    }

    public function testAnUnknownRoutingIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processVariants(['routing' => 'attributes']);
    }

    /**
     * @param array<string, string> $projectProperties
     *
     * @return array<string, string>
     */
    private function processVariantProperties(array $projectProperties): array
    {
        return $this->processVariants(['properties' => $projectProperties])['properties'];
    }

    /**
     * Runs the bundle's prepend and the project config through the configuration, as the kernel does.
     *
     * @param array<string, mixed> $projectVariants
     *
     * @return array{routing: string, query_parameter: string, properties: array<string, string>}
     */
    private function processVariants(array $projectVariants): array
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', \sys_get_temp_dir());

        $extension = (new SuluProductBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $configs = $builder->getExtensionConfig('sulu_product');
        $configs[] = ['variants' => $projectVariants];

        $configuration = $extension->getConfiguration([], $builder);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        /** @var array{variants: array{routing: string, query_parameter: string, properties: array<string, string>}} $processed */
        $processed = (new Processor())->processConfiguration($configuration, $configs);

        return $processed['variants'];
    }
}
