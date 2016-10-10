<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DependencyInjection;

use Sulu\Bundle\PersistenceBundle\DependencyInjection\PersistenceExtensionTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluProductExtension extends Extension implements PrependExtensionInterface
{
    use PersistenceExtensionTrait;

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('sulu_product', $config);
        $container->setParameter('sulu_product.content-type.product.template', $config['types']['product']['template']);
        $container->setParameter('sulu_product.category_root_key', $config['category_root_key']);
        $container->setParameter('sulu_product.default_currency', $config['default_currency']);
        $container->setParameter('sulu_product.default_formatter_locale', $config['default_formatter_locale']);
        $container->setParameter('sulu_product.display_recurring_prices', $config['display_recurring_prices']);
        $container->setParameter('sulu_product.fallback_locale', $config['fallback_locale']);
        $container->setParameter('sulu_product.fixtures.attributes', $config['fixtures']['attributes']);
        $container->setParameter('sulu_product.locales', $config['locales']);
        $container->setParameter('sulu_product.template', $config['template']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $this->configurePersistence($config['objects'], $container);
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        if ($container->hasExtension('sulu_validation')) {
            $container->prependExtensionConfig(
                'sulu_validation',
                [
                    'schemas' => [
                        'get_product' => '@SuluProductBundle/Validation/Product/getActionSchema.json',
                    ],
                ]
            );
        }
    }
}
