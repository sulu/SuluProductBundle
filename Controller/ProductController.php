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

use Doctrine\Common\Persistence\ObjectManager;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Admin\SuluProductAdmin;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingProductAttributeException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductChildrenExistException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\ProductBundle\Product\ProductLocaleManager;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\MissingArgumentException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\FieldDescriptor;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends RestController implements ClassResourceInterface, SecuredControllerInterface
{
    protected static $entityName = 'SuluProductBundle:Product';

    protected static $entityKey = 'products';

    /**
     * Returns the repository object for AdvancedProduct.
     *
     * @return ProductManagerInterface
     */
    protected function getManager()
    {
        return $this->get('sulu_product.product_manager');
    }

    /**
     * Returns all fields that can be used by list.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function fieldsAction(Request $request)
    {
        return $this->handleView(
            $this->view(
                array_values(
                    array_diff_key(
                        $this->getManager()->getFieldDescriptors($this->getLocale($request)),
                        [
                            'statusId' => false,
                            'categoryIds' => false,
                        ]
                    )
                ),
                200
            )
        );
    }

    /**
     * Retrieves and shows a product with the given ID.
     *
     * @param Request $request
     * @param int $id product ID
     *
     * @return Response
     */
    public function getAction(Request $request, $id)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));
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
     * Returns a list of products.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function cgetAction(Request $request)
    {
        $filter = $this->getManager()->getFilters($request);
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        if ($request->get('flat') == 'true') {
            $filterFieldDescriptors = $this->getManager()->getFilterFieldDescriptors();
            $fieldDescriptors = $this->getManager()->getFieldDescriptors(
                $locale
            );

            $list = $this->flatResponse(
                $request,
                $filter,
                $filterFieldDescriptors,
                $fieldDescriptors,
                static::$entityName
            );
        } elseif ($request->get('ids') !== '') {
            $list = new CollectionRepresentation(
                $this->getManager()->findAllByIdsAndLocale($locale, $request->get('ids')),
                self::$entityKey
            );
        } else {
            $list = new CollectionRepresentation(
                $this->getManager()->findAllByLocale($locale, $filter),
                self::$entityKey
            );
        }

        $view = $this->view($list, 200);

        return $this->handleView($view);
    }

    /**
     * @param string $entityName
     * @param FieldDescriptor[] $fieldDescriptors
     *
     * @return DoctrineListBuilder
     */
    protected function getListBuilder($entityName, $fieldDescriptors)
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

        return $listBuilder;
    }

    /**
     * Processes the request for a flat response.
     *
     * @param Request $request
     * @param array $filter
     * @param array $filterFieldDescriptors
     * @param array $fieldDescriptors
     * @param string $entityName
     *
     * @return ListRepresentation
     */
    protected function flatResponse(
        Request $request,
        $filter,
        $filterFieldDescriptors,
        $fieldDescriptors,
        $entityName
    ) {
        $listBuilder = $this->getListBuilder($entityName, $fieldDescriptors);

        foreach ($filter as $key => $value) {
            if (is_array($value)) {
                $listBuilder->in($filterFieldDescriptors[$key], $value);
            } else {
                $listBuilder->where($filterFieldDescriptors[$key], $value);
            }
        }

        $ids = null;
        if (null !== $request->get('ids')) {
            $ids = array_filter(explode(',', $request->get('ids', '')));
            $listBuilder->in($fieldDescriptors['id'], $ids);
            $listBuilder->limit(count($ids));
        }

        // Only add group by id if categories are processed.
        $fieldsParam = $request->get('fields');
        $fields = explode(',', $fieldsParam);
        if (isset($filter['categories']) ||
            !$fieldsParam ||
            array_search('categories', $fields) !== false
        ) {
            $listBuilder->addGroupBy($fieldDescriptors['id']);
        }

        if (json_decode($request->get('disablePagination'))) {
            return [
                '_embedded' => [
                    'products' => $listBuilder->execute(),
                ],
            ];
        }

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
     * @param Request $request
     * @param int $id product ID
     *
     * @return Response
     */
    public function putAction(Request $request, $id)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        try {
            $product = $this->getManager()->save(
                $request->request->all(),
                $locale,
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
            $exception = new MissingArgumentException(static::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        } catch (EntityIdAlreadySetException $exc) {
            $view = $this->view($exc->toArray(), 400);
        } catch (ProductException $exc) {
            $exception = new RestException($exc->getMessage(), $exc->getCode());
            $view = $this->view($exception->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Creates and stores a new product.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        try {
            $product = $this->getManager()->save(
                $request->request->all(),
                $locale,
                $this->getUser()->getId()
            );

            $view = $this->view($product, 200);
        } catch (ProductDependencyNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 400);
        } catch (MissingProductAttributeException $exc) {
            $exception = new MissingArgumentException(static::$entityName, $exc->getAttribute());
            $view = $this->view($exception->toArray(), 400);
        } catch (ProductException $exc) {
            $exception = new RestException($exc->getMessage(), $exc->getCode());
            $view = $this->view($exception->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Delete a product with the given id.
     *
     * @param Request $request
     * @param int $id product id
     *
     * @return Response
     */
    public function deleteAction(Request $request, $id)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

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
     * {@inheritdoc}
     */
    public function getSecurityContext()
    {
        return SuluProductAdmin::CONTEXT_PRODUCTS;
    }

    /**
     * Make a partial update of a product.
     *
     * @param Request $request
     * @param int $id
     *
     * @return Response
     */
    public function patchAction(Request $request, $id)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        try {
            $product = $this->getManager()->partialUpdate(
                $request->request->all(),
                $locale,
                $this->getUser()->getId(),
                $id
            );

            $this->getEntityManager()->flush();

            $view = $this->view($product, 200);
        } catch (ProductNotFoundException $exc) {
            $exception = new EntityNotFoundException($exc->getEntityName(), $exc->getId());
            $view = $this->view($exception->toArray(), 404);
        }

        return $this->handleView($view);
    }

    /**
     * @return ObjectManager
     */
    private function getEntityManager()
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    /**
     * @return ProductLocaleManager
     */
    private function getProductLocaleManager()
    {
        return $this->get('sulu_product.product_locale_manager');
    }
}
