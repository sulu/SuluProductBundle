<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Controller;

use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations\Get;

class AttributeController extends RestController implements ClassResourceInterface
{
    /**
     * Returns the manager for Attributes
     *
     * @return AttributeManagerInterface
     */
    private function getManager()
    {
        return $this->get('sulu_product.attribute_manager');
    }

    /**
     * returns all fields that can be used by list
     * @Get("attributes/fields")
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return mixed
     */
    public function getFieldsAction(Request $request)
    {
        return $this->handleView(
            $this->view(array_values($this->getManager()->getFieldDescriptors($this->getLocale($request))))
        );
    }

    /**
     * Retrieves and shows a attribute with the given ID
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product attribute ID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request, $id)
    {
        $view = $this->view([], 200);
        return $this->handleView($view);
    }

    /**
     * Returns a list of attributes
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cgetAction(Request $request)
    {
        $view = $this->view([], 200);
        return $this->handleView($view);
    }

    /**
     * Change a attribute by the given attribute id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product ID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function putAction(Request $request, $id)
    {
        $view = $this->view([], 200);
        return $this->handleView($view);
    }

    /**
     * Creates and stores a new attribute.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postAction(Request $request)
    {
        $view = $this->view([], 200);
        return $this->handleView($view);
    }

    /**
     * Delete an account attribute with the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $id)
    {
        $view = $this->view([], 200);
        return $this->handleView($view);
    }
}
