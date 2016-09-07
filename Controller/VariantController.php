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

use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller is responsible for managing variants to a specific product.
 */
class VariantController extends RestController implements ClassResourceInterface
{
    protected static $entityName = 'SuluProductBundle:Product';

    protected static $entityKey = 'products';

    /**
     * Returns the manager for products.
     *
     * @return ProductManagerInterface
     */
    private function getManager()
    {
        return $this->get('sulu_product.product_manager');
    }

    /**
     * Retrieves and shows the variant entIdwith the given ID for the parent product.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $parentId
     * @param int $id product ID
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request, $parentId, $id)
    {
        $locale = $this->getLocale($request);
        $view = $this->responseGetById(
            $id,
            function ($id) use ($locale, $parentId) {
                $product = $this->getManager()->findByIdAndLocale($id, $locale);

                if ($product !== null && $product->getParent() && $product->getParent()->getId() == $parentId) {
                    return $product;
                } else {
                    return null;
                }
            }
        );

        return $this->handleView($view);
    }

    /**
     * Returns a list of products.
     *
     * @param Request $request
     * @param $parentId
     *
     * @return Response
     */
    public function cgetAction(Request $request, $parentId)
    {
        if ($request->get('flat') == 'true') {
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

            $listBuilder->where($fieldDescriptors['parent'], $parentId);

            // TODO, should only be added if "categories" are requested
            $listBuilder->addGroupBy($fieldDescriptors['id']);

            $list = new ListRepresentation(
                $listBuilder->execute(),
                self::$entityKey,
                'get_products',
                $request->query->all(),
                $listBuilder->getCurrentPage(),
                $listBuilder->getLimit(),
                $listBuilder->count()
            );
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
     * Adds a new variant to this product.
     *
     * @param Request $request
     * @param $parentId
     *
     * @return Response
     */
    public function postAction(Request $request, $parentId)
    {
        try {
            $variant = $this->getManager()->addVariant($parentId, $request->get('id'), $this->getLocale($request));

            $view = $this->view($variant, 200);
        } catch (ProductNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Removes a variant from a product.
     *
     * @param Request $request
     * @param $parentId
     * @param $id
     *
     * @return Response
     */
    public function deleteAction(Request $request, $parentId, $id)
    {
        try {
            $this->getManager()->removeVariant($parentId, $id);

            $view = $this->view(null, 204);
        } catch (ProductNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }

        return $this->handleView($view);
    }
}
