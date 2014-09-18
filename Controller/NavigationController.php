<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Responsible for creating the tab navigations
 * @package Sulu\Bundle\ProductBundle\Controller
 */
class NavigationController extends Controller
{
    const SERVICE_NAME = 'sulu_product.admin.content_navigation';

    /**
     * Returns the tabs for simple products
     * @return JsonResponse
     */
    public function productAction()
    {
        return $this->getJsonResponse('product');
    }

    /**
     * Returns the tabs for products with variants
     * @return JsonResponse
     */
    public function productWithVariantsAction()
    {
        return $this->getJsonResponse('product-with-variants');
    }

    /**
     * Returns the tabs for product addons
     * @return JsonResponse
     */
    public function productAddonAction()
    {
        return $this->getJsonResponse('product-addon');
    }

    /**
     * Returns the tabs for product sets
     * @return JsonResponse
     */
    public function productSetAction()
    {
        return $this->getJsonResponse('product-set');
    }

    /**
     * Creates the json response for the tabs for the given groups
     * @param $group
     * @return JsonResponse
     */
    private function getJsonResponse($group)
    {
        if ($this->has(self::SERVICE_NAME)) {
            $contentNavigation = $this->get(self::SERVICE_NAME);

            return new JsonResponse($contentNavigation->toArray($group));
        }

        return new JsonResponse(array());
    }
} 
