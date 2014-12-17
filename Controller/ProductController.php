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
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingProductAttributeException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductChildrenExistException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\MissingArgumentException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\Get;

class ProductController extends RestController implements ClassResourceInterface, SecuredControllerInterface
{
    protected static $entityName = 'SuluProductBundle:Product';

    protected static $entityKey = 'products';

    /**
     * Returns the repository object for AdvancedProduct
     *
     * @return ProductManagerInterface
     */
    private function getManager()
    {
        return $this->get('sulu_product.product_manager');
    }

    /**
     * returns all fields that can be used by list
     * @Get("products/fields")
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return mixed
     */
    public function getFieldsAction(Request $request)
    {
        return $this->handleView(
            $this->view(
                array_values(
                    array_diff_key(
                        $this->getManager()->getFieldDescriptors($this->getLocale($request)),
                        array(
                            'statusId' => false
                        )
                    )
                ),
                200
            )
        );
    }

    /**
     * Retrieves and shows a product with the given ID
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product ID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);
        $view = $this->responseGetById(
            $id,
            function ($id) use ($locale) {
                /** @var Product $product */
                $product = $this->getManager()->findByIdAndLocale($id, $locale);

                return $product;
            }
        );

        return $this->handleView($view);
    }

    /**
     * Get filters provided by the request
     *
     * @param Request $request
     * @return List $filter
     */
    protected function getFilters(Request $request)
    {
        $filter = array();

        $statuses = $request->get('status');
        if ($statuses) {
            $filter['status'] = explode(',', $statuses);
        }

        $statusIds = $request->get('status_id');
        if ($statusIds) {
            $filter['status_id'] = explode(',', $statusIds);
        }

        $types = $request->get('type');
        if ($types) {
            $filter['type_id'] = explode(',', $types);
        }

        $typeIds = $request->get('type_id');
        if ($typeIds) {
            $filter['type_id'] = explode(',', $typeIds);
        }

        $supplierId = $request->get('supplier_id');
        if ($supplierId) {
            $filter['accounts_supplier_id'] = $supplierId;
        }

        $isDeprecated = $request->get('is_deprecated');
        if ($isDeprecated !== null) {
            $filter['is_deprecated'] = $isDeprecated;
        }

        $parent = $request->get('parent');
        if ($parent) {
            $filter['parent'] = ($parent == 'null') ? null : $parent;
        }

        $categories = $request->get('categories');
        if ($categories) {
            $filter['categories'] = ($categories == 'null') ? null : $categories;
        }
        return $filter;
    }

    /**
     * Returns a list of products
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cgetAction(Request $request)
    {
        $filter = $this->getFilters($request);

        if ($request->get('flat') == 'true') {
            $filterFieldDescriptors = $this->getManager()->getFilterFieldDescriptors();
            $fieldDescriptors = $this->getManager()->getFieldDescriptors(
                $this->getLocale($request)
            );

            $list = $this->flatResponse(
                $request,
                $filter,
                $filterFieldDescriptors,
                $fieldDescriptors,
                self::$entityName
            );
        } elseif ($request->get('ids') !== '') {
            $list = new CollectionRepresentation(
                $this->getManager()->findAllByIdsAndLocale($this->getLocale($request), $request->get('ids')),
                self::$entityKey
            );
        } else {
            $list = new CollectionRepresentation(
                $this->getManager()->findAllByLocale($this->getLocale($request), $filter),
                self::$entityKey
            );
        }

        $view = $this->view($list, 200);

        return $this->handleView($view);
    }

    /**
     * Processes the request for a flat response
     *
     * @param Request $request
     *
     * @return list
     */
    protected function flatResponse($request, $filter, $filterFieldDescriptors, $fieldDescriptors, $entityName)
    {
        /** @var RestHelperInterface $restHelper */
        $restHelper = $this->get('sulu_core.doctrine_rest_helper');

        /** @var DoctrineListBuilderFactory $factory */
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');

        $listBuilder = $factory->create($entityName);

        $restHelper->initializeListBuilder(
            $listBuilder,
            $fieldDescriptors
        );

        foreach ($filter as $key => $value) {
            if (is_array($value)) {
                $listBuilder->in($filterFieldDescriptors[$key], $value);
            } else {
                $listBuilder->where($filterFieldDescriptors[$key], $value);
            }
        }

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

        return $list;
    }

    /**
     * Change a product entry by the given product id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product ID
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function putAction(Request $request, $id)
    {
        try {
            $product = $this->getManager()->save(
                $request->request->all(),
                $this->getLocale($request),
                $this->getUser()->getId(),
                $id
            );

            $view = $this->view($product, 200);
        } catch (ProductNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        } catch (ProductDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (MissingProductAttributeException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        } catch (EntityIdAlreadySetException $exc) {
            $view = $this->view($exc->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Creates and stores a new product.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postAction(Request $request)
    {
        try {
            $product = $this->getManager()->save(
                $request->request->all(),
                $this->getLocale($request),
                $this->getUser()->getId()
            );

            $view = $this->view($product, 200);
        } catch (ProductDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (MissingProductAttributeException $exc) {
            $exception = new MissingArgumentException(self::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Delete a product with the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $id)
    {
        $locale = $this->getLocale($request);

        $delete = function ($id) use ($locale) {
            try {
                $this->getManager()->delete($id, $this->getUser()->getId());
            } catch (ProductChildrenExistException $exc) {
                throw new RestException('Deletion not allowed, because the product has sub products', 400);
            }
        };
        $view = $this->responseDelete($id, $delete);

        return $this->handleView($view);
    }

    /**
     * {@inheritDoc}
     */
    public function getSecurityContext()
    {
        return 'sulu.product.products';
    }

    /**
     * Make a partial update of a product
     *
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function patchAction(Request $request, $id)
    {
        try {
            $product = $this->getManager()->partialUpdate(
                $request->request->all(),
                $this->getLocale($request),
                $this->getUser()->getId(),
                $id
            );

            $view = $this->view($product, 200);
        } catch (ProductNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }

        return $this->handleView($view);
    }
}
