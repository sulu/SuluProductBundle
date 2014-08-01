<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

/**
 * CompilerPass, which instantiates the route provider only when the required dependencies exist
 * @package Sulu\Bundle\WebsiteBundle\DependencyInjection\Compiler
 */
class AddRoutingPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('sulu_core.webspace.request_analyzer')) {
            $container->setDefinition(
                'sulu_product.product_route_provider',
                new Definition(
                    'Sulu\Bundle\ProductBundle\Routing\ProductRouteProvider',
                    array(
                        new Reference('sulu_core.webspace.request_analyzer'),
                    )
                )
            );

            $container->setDefinition(
                'sulu_product.product_router',
                new Definition(
                    new Parameter('cmf_routing.dynamic_router.class'),
                    array(
                        new Reference('router.request_context'),
                        new Definition(
                            new Parameter('cmf_routing.nested_matcher.class'),
                            array(
                                new Reference('sulu_product.product_route_provider'),
                                new Reference('cmf_routing.final_matcher')
                            )
                        ),
                        new Reference('cmf_routing.generator'),
                        new Parameter('cmf_routing.uri_filter_regexp'),
                        new Reference('event_dispatcher'),
                        new Reference('sulu_product.product_route_provider'),
                    )
                )
            );

            $container->getDefinition('sulu_product.product_router')->addMethodCall(
                'setRequest',
                array(new Reference('request', ContainerInterface::NULL_ON_INVALID_REFERENCE, false))
            );
            $container->getDefinition('sulu_product.product_router')->addMethodCall(
                'addRouteEnhancer',
                array(new Reference('cmf_routing.enhancer.route_content'), 100)
            );
        }
    }
}
