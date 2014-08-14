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

class NavigationController extends Controller
{
    const SERVICE_NAME = 'sulu_product.admin.content_navigation';

    public function productAction()
    {
        return $this->getJsonResponse('product');
    }

    public function productWithVariantsAction()
    {
        return $this->getJsonResponse('product-with-variants');
    }

    public function productAddonAction()
    {
        return $this->getJsonResponse('product-addon');
    }

    public function productSetAction()
    {
        return $this->getJsonResponse('product-set');
    }

    /**
     * @param $group
     * @return JsonResponse
     */
    private function getJsonResponse($group)
    {
        if ($this->has(self::SERVICE_NAME)) {
            $contentNavigation = $this->get(self::SERVICE_NAME);

            return new JsonResponse(json_encode($contentNavigation->toArray($group)));
        }

        return new JsonResponse(array());
    }
} 
