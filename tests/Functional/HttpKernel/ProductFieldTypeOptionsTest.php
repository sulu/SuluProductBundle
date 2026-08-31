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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[CoversClass(SuluProductBundle::class)]
class ProductFieldTypeOptionsTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    /**
     * A multi selection renders its items from the list endpoint, so a display property the list does
     * not provide leaves every selected item blank in the admin. Single selections load the whole item
     * from the detail endpoint instead and are covered by ProductControllerTest.
     */
    public function testEveryMultiSelectionDisplayPropertyIsProvidedByItsList(): void
    {
        self::bootKernel();

        /** @var FieldDescriptorFactoryInterface $fieldDescriptorFactory */
        $fieldDescriptorFactory = self::getContainer()->get('sulu_core.list_builder.field_descriptor_factory');

        $assertedProperties = 0;
        foreach ($this->getSelectionListOverlayTypes() as $fieldTypeName => $type) {
            if (!\str_starts_with($fieldTypeName, 'selection.')) {
                continue;
            }

            $fieldDescriptors = $fieldDescriptorFactory->getFieldDescriptors($type['list_key']);
            $this->assertIsArray($fieldDescriptors, \sprintf('List "%s" of "%s" does not exist.', $type['list_key'], $fieldTypeName));

            foreach ($type['display_properties'] as $displayProperty) {
                $this->assertArrayHasKey(
                    $displayProperty,
                    $fieldDescriptors,
                    \sprintf('Display property "%s" of "%s" is not provided by list "%s".', $displayProperty, $fieldTypeName, $type['list_key']),
                );
                ++$assertedProperties;
            }
        }

        $this->assertGreaterThan(0, $assertedProperties, 'No selection display properties were checked.');
    }

    /**
     * @return \Generator<string, array{list_key: string, display_properties: array<int, string>}>
     */
    private function getSelectionListOverlayTypes(): \Generator
    {
        /** @var array<string, array<string, array{types: array<string, array{list_key: string, display_properties?: array<int, string>}>}>> $fieldTypeOptions */
        $fieldTypeOptions = $this->getPrependedAdminConfig()['field_type_options'] ?? [];

        foreach ($fieldTypeOptions as $selectionType => $fieldTypes) {
            foreach ($fieldTypes as $fieldTypeName => $fieldType) {
                foreach ($fieldType['types'] as $type) {
                    if (!isset($type['display_properties'])) {
                        continue;
                    }

                    yield $selectionType . '.' . $fieldTypeName => [
                        'list_key' => $type['list_key'],
                        'display_properties' => $type['display_properties'],
                    ];
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getPrependedAdminConfig(): array
    {
        $builder = new ContainerBuilder();
        $builder->registerExtension(new class() implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            // Symfony 8 dropped this from ExtensionInterface; the narrower "false"
            // still satisfies the "string|false" the 6.4/7.x interface declares.
            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'sulu_admin';
            }
        });

        $instanceof = [];
        $loader = new PhpFileLoader($builder, new FileLocator());

        (new SuluProductBundle())->prependExtension(
            new ContainerConfigurator($builder, $loader, $instanceof, __FILE__, __FILE__),
            $builder,
        );

        /** @var array<string, mixed> $config */
        $config = $builder->getExtensionConfig('sulu_admin')[0] ?? [];

        return $config;
    }
}
