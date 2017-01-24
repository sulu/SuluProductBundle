<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\RouteBundle\Manager\RouteManagerInterface;

class ProductRouteManager implements ProductRouteManagerInterface
{
    public static $routeEntityName = 'Sulu\Bundle\ProductBundle\Entity\ProductTranslation';

    /**
     * @var RouteManagerInterface
     */
    private $routeManager;

    /**
     * @var array
     */
    private $routeMappings;

    /**
     * @param RouteManagerInterface $routeManager
     * @param array $routeMappings
     */
    public function __construct(RouteManagerInterface $routeManager, array $routeMappings)
    {
        $this->routeManager = $routeManager;
        $this->routeMappings = $routeMappings;
    }

    /**
     * {@inheritdoc}
     */
    public function saveRoute(ProductTranslation $productTranslation, $path = null)
    {
        // If routing is not enabled, skip or if productTranslation wasn't created yet,
        // this function will be triggered by translation_created_event later.
        if (!$this->isRoutingEnabled() || !$productTranslation->getId()) {
            return;
        }

        // Check if route exists create.
        if (!$productTranslation->getRoute()) {
            $this->routeManager->create($productTranslation, $path);
        } elseif ($path) {
            // Only update route if a path is given.
            $this->routeManager->update($productTranslation, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isRoutingEnabled()
    {
        return array_key_exists(static::$routeEntityName, $this->routeMappings);
    }
}
