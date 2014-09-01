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

use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations\Get;

use Hateoas\Representation\CollectionRepresentation;

use Symfony\Component\HttpFoundation\Request;

use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\MissingArgumentException;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeAttributeException;

class ValuesController extends RestController implements ClassResourceInterface
{
    protected static $entityName = 'SuluProductBundle:AttributeValue';

    protected static $entitykey = 'attributeValues';

    /**
     * Returns the manager for AttributeValues
     *
     * @return AttributeValuesManager
     */
    private function getManager()
    {
        return $this->get('sulu_product.attribute_value_manager');
    }

    /**
     * returns all fields that can be used by list
     * @Get("attributes/{id}/values/fields")
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
     * Retrieves and shows a attributeValue with the given ID
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id attribute ID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);
        $view = $this->responseGetById(
            $id,
            function ($id) use ($locale) {
                $attributeValue = $this->getManager()->findByIdAndLocale($id, $locale);

                return $attributeValue;
            }
        );

        return $this->handleView($view);
    }

    /**
     * Change a attribute by the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id the attribute value id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function putAction(Request $request, $attributeId, $attributeValueId)
    {
        try {
            $attribute = $this->getManager()->save(
                $request->request->all(),
                $this->getLocale($request),
                $attributeId,
                $attributeValueId
            );
            $view = $this->view($attribute, 200);
        } catch (AttributeDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (AttributeNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (MissingAttributeAttributeException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }
        return $this->handleView($view);
    }

    /**
     * Creates and stores a new attribute value.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postAction(Request $request, $attributeId)
    {
        try {
            $attributeValue = $this->getManager()->save(
                $request->request->all(),
                $this->getLocale($request),
                $attributeId
            );
            $view = $this->view($attributeValue, 200);
        } catch (AttributeDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (MissingAttributeAttributeException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }
        return $this->handleView($view);
    }

    /**
     * Delete an product attribute value with the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id the attribute id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);

        $delete = function ($id) use ($locale) {
            $this->getManager()->delete($id, $this->getUser()->getId());
        };
        $view = $this->responseDelete($id, $delete);
        return $this->handleView($view);
    }
}
