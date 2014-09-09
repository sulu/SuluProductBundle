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
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeNotFoundException;

/**
 * Makes product attributes available through a REST API
 * @package Sulu\Bundle\ProductBundle\Controller
 */
class AttributeController extends RestController implements ClassResourceInterface
{
    protected static $entityName = 'SuluProductBundle:Attribute';

    protected static $entityKey = 'attributes';

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
     * @param integer $id attribute ID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);
        try {
            $attribute = $this->getManager()->findByIdAndLocale($id, $locale);
            $view = $this->view($attribute, 200);
        } catch (AttributeNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }
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
        if ($request->get('flat') == 'true') {
            $list = $this->getListRepresentation($request);
        } else {
            $list = new CollectionRepresentation(
                $this->getManager()->findAllByLocale($this->getLocale($request)),
                self::$entityKey
            );
        }

        $view = $this->view($list, 200);
        return $this->handleView($view);
    }

    /**
     * Returns a list representation
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return Sulu\Component\Rest\ListBuilder\ListRepresentation
     */
    private function getListRepresentation($request)
    {
        /** @var RestHelperInterface $restHelper */
        $restHelper = $this->get('sulu_core.doctrine_rest_helper');

        /** @var DoctrineListBuilderFactory $factory */
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');

        $listBuilder = $factory->create(self::$entityName);

        $restHelper->initializeListBuilder(
            $listBuilder,
            $this->getManager()->getFieldDescriptors($this->getLocale($request))
        );

        $list = new ListRepresentation(
            $listBuilder->execute(),
            self::$entityKey,
            'get_attributes',
            $request->query->all(),
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
     * @param integer $id the attribute id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function putAction(Request $request, $id)
    {
        try {
            $attribute = $this->getManager()->save(
                $request->request->all(),
                $this->getLocale($request),
                $this->getUser()->getId(),
                $id
            );
            $view = $this->view($attribute, 200);
        } catch (AttributeDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (AttributeNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        } catch (MissingAttributeException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }
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
        try {
            $attribute = $this->getManager()->save(
                $request->request->all(),
                $this->getLocale($request),
                $this->getUser()->getId()
            );
            $view = $this->view($attribute, 200);
        } catch (AttributeDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (MissingAttributeException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }
        return $this->handleView($view);
    }

    /**
     * Delete an product attribute with the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id the attribute id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $id)
    {
        try {
            $this->getManager()->delete($id, $this->getUser()->getId());
            $view = $this->view($id, 204);
        } catch (AttributeNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }
        return $this->handleView($view);
    }
}
