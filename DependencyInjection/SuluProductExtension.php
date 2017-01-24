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
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductTypes\LoadProductTypes;
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

        $container->setParameter('sulu_product.product_types_map', $this->retrieveProductTypesMap($container));

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
                        // Products
                        'get_product' => '@SuluProductBundle/Validation/localeSchema.json',
                        // Product Media
                        'get_product_media_fields' => '@SuluProductBundle/Validation/localeSchema.json',
                        'cget_product_media' => '@SuluProductBundle/Validation/localeSchema.json',
                        'put_product_media' => '@SuluProductBundle/Validation/ProductMedia/putActionSchema.json',
                        // Variants
                        'get_product_variants' => '@SuluProductBundle/Validation/localeSchema.json',
                        'post_product_variant' => '@SuluProductBundle/Validation/Variants/postPutActionSchema.json',
                        'put_product_variant' => '@SuluProductBundle/Validation/Variants/postPutActionSchema.json',
                        'get_product_productvariant_attribute_fields' => '@SuluProductBundle/Validation/localeSchema.json',
                        'get_product_productvariant_attributes' => '@SuluProductBundle/Validation/localeSchema.json',
                        // Content
                        'get_product_content' => '@SuluProductBundle/Validation/localeSchema.json',
                        'put_product_content' => '@SuluProductBundle/Validation/localeSchema.json',
                    ],
                ]
            );
        }
        if ($container->hasExtension('fos_rest')) {
            $container->prependExtensionConfig(
                'fos_rest',
                [
                    'exception' => [
                        'codes' => [
                            'Sulu\Bundle\ProductBundle\Product\Exception\AttributeNotFoundException' => 400,
                            'Sulu\Bundle\ProductBundle\Product\Exception\ProductNotFoundException' => 400,
                            'Sulu\Component\Rest\Exception\EntityNotFoundException' => 404,
                            'Sulu\Bundle\ProductBundle\Product\Exception\ProductException' => 400,
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Returns key to id mapping for product-types.
     * Processes product-types fixtures xml.
     *
     * @return array
     */
    private function retrieveProductTypesMap()
    {
        $productTypeMap = [];
        LoadProductTypes::processProductTypesFixtures(
            function (\DOMElement $element) use (&$productTypeMap) {
                $productTypeMap[$element->getAttribute('key')] = $element->getAttribute('id');
            }
        );

        return $productTypeMap;
    }
}
