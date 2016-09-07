<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Controller;

use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeValueNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeValueException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\MissingArgumentException;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Makes product attribute values available through a REST API.
 */
class ValueController extends RestController implements ClassResourceInterface
{
    protected static $entityName = 'SuluProductBundle:AttributeValue';

    protected static $entityKey = 'attributeValues';

    /**
     * Returns the manager for AttributeValues.
     *
     * @return AttributeValueManager
     */
    private function getManager()
    {
        return $this->get('sulu_product.attribute_value_manager');
    }

    /**
     * Returns the manager for Attributes.
     *
     * @return AttributeManager
     */
    private function getAttributeManager()
    {
        return $this->get('sulu_product.attribute_manager');
    }

    /**
     * returns all fields that can be used by list.
     *
     * @Get("attributes/values/fields")
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return mixed
     */
    public function getFieldsAction(Request $request)
    {
        return $this->handleView(
            $this->view(array_values($this->getManager()->getFieldDescriptors($this->getLocale($request))))
        );
    }

    /**
     * Retrieves and shows a attribute with the given ID.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id attribute value ID
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request, $attributeId, $attributeValueId)
    {
        $locale = $this->getLocale($request);
        try {
            $this->getAttributeManager()->findByIdAndLocale($attributeId, $locale);
        } catch (AttributeNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);

            return $this->handleView($view);
        }

        try {
            $attributeValue = $this->getManager()->findByIdAndLocale($attributeValueId, $locale);
            $view = $this->view($attributeValue, 200);
        } catch (AttributeValueNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }

        return $this->handleView($view);
    }

    /**
     * Retrieves and shows a attributeValue with the given ID.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id attribute ID
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cgetAction(Request $request, $id)
    {
        try {
            if ($request->get('flat') == 'true') {
                $list = $this->getListRepresentation($request, $id);
            } else {
                $list = new CollectionRepresentation(
                    $this->getManager()->findAllByAttributeIdAndLocale($this->getLocale($request), $id),
                    self::$entityKey
                );
            }
            $view = $this->view($list, 200);
        } catch (AttributeNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }

        return $this->handleView($view);
    }

    /**
     * Returns a list representation.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return Sulu\Component\Rest\ListBuilder\ListRepresentation
     */
    private function getListRepresentation($request, $id)
    {
        /** @var RestHelperInterface $restHelper */
        $restHelper = $this->get('sulu_core.doctrine_rest_helper');

        /** @var DoctrineListBuilderFactory $factory */
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');

        $listBuilder = $factory->create(self::$entityName);
        $fieldDescriptors = $this->getManager()->getFieldDescriptors($this->getLocale($request));
        $restHelper->initializeListBuilder(
            $listBuilder,
            $fieldDescriptors
        );

        $listBuilder->where($fieldDescriptors['attribute_id'], $id);

        $list = new ListRepresentation(
            $listBuilder->execute(),
            self::$entityKey,
            'get_attribute_values',
            array_merge($request->query->all(), ['id' => $id]),
            $listBuilder->getCurrentPage(),
            $listBuilder->getLimit(),
            $listBuilder->count()
        );

        return $list;
    }

    /**
     * Change a attribute by the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id the attribute value id
     *
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
        } catch (AttributeValueNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        } catch (MissingAttributeValueException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Creates and stores a new attribute value.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
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
        } catch (MissingAttributeValueException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Delete an product attribute value for the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id the attribute id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $attributeId, $attributeValueId)
    {
        try {
            $this->getManager()->delete($attributeValueId, $this->getUser()->getId());
            $view = $this->view($attributeValueId, 204);
        } catch (AttributeValueNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }

        return $this->handleView($view);
    }
}
