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

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Admin\SuluProductAdmin;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductVariantManagerInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller is responsible for managing variants to a specific product.
 */
class VariantController extends RestController implements ClassResourceInterface, SecuredControllerInterface
{
    protected static $entityName = 'SuluProductBundle:Product';
    protected static $entityKey = 'products';

    /**
     * {@inheritdoc}
     */
    public function getSecurityContext()
    {
        return SuluProductAdmin::CONTEXT_PRODUCTS;
    }

    /**
     * Retrieves and shows the variant with the given ID for the parent product.
     *
     * @param Request $request
     * @param int $parentId
     * @param int $variantId
     *
     * @return Response
     */
    public function getAction(Request $request, $parentId, $variantId)
    {
        $locale = $this->getLocale($request);
        $view = $this->responseGetById(
            $variantId,
            function ($id) use ($locale, $parentId) {
                $product = $this->getProductManager()->findByIdAndLocale($id, $locale);

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
     * Returns a list of product variants for the requested product.
     *
     * @param Request $request
     * @param int $parentId
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

            $fieldDescriptors = $this->getProductManager()->getFieldDescriptors($this->getLocale($request));

            $restHelper->initializeListBuilder(
                $listBuilder,
                $fieldDescriptors
            );

            $listBuilder->where($fieldDescriptors['parent'], $parentId);

            // Only add group by id if categories are processed.
            $fieldsParam = $request->get('fields');
            $fields = explode(',', $fieldsParam);
            if (isset($filter['categories'])
                || !$fieldsParam
                || array_search('categories', $fields) !== false
            ) {
                $listBuilder->addGroupBy($fieldDescriptors['id']);
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
        } else {
            $list = new CollectionRepresentation(
                $this->getProductManager()->findAllByLocale($this->getLocale($request)),
                self::$entityKey
            );
        }

        $view = $this->view($list, 200);

        return $this->handleView($view);
    }

    /**
     * Adds a new variant to given product.
     *
     * @param Request $request
     * @param int $parentId
     *
     * @return Response
     */
    public function postAction(Request $request, $parentId)
    {
        $requestData = $request->request->all();
        $userId = $this->getUser()->getId();
        $locale = $this->getLocale($request);

        $variant = $this->getProductVariantManager()->createVariant(
            $parentId,
            $requestData,
            $locale,
            $userId
        );

        $this->getEntityManager()->flush();

        $apiVariant = $this->getProductFactory()->createApiEntity($variant, $locale);

        $view = $this->view($apiVariant, 200);

        return $this->handleView($view);
    }

    /**
     * Updates an existing variant to given product.
     *
     * @param Request $request
     * @param int $parentId
     * @param int $variantId
     *
     * @throws ProductException
     *
     * @return Response
     */
    public function putAction(Request $request, $parentId, $variantId)
    {
        $requestData = $request->request->all();
        $userId = $this->getUser()->getId();
        $locale = $this->getLocale($request);

        $variant = $this->getProductVariantManager()->updateVariant(
            $variantId,
            $requestData,
            $locale,
            $userId
        );

        if ($variant->getParent()->getId() !== (int) $parentId) {
            throw new ProductException('Variant does not exists for given product.');
        }

        $this->getEntityManager()->flush();

        $apiVariant = $this->getProductFactory()->createApiEntity($variant, $locale);

        $view = $this->view($apiVariant, 200);

        return $this->handleView($view);
    }

    /**
     * Removes a variant of product.
     *
     * @param int $parentId
     * @param int $variantId
     *
     * @throws ProductException
     *
     * @return Response
     */
    public function deleteAction($parentId, $variantId)
    {
        $variant = $this->getProductVariantManager()->deleteVariant($variantId);

        if ($variant->getParent()->getId() !== (int) $parentId) {
            throw new ProductException('Variant does not exists for given product.');
        }
        $this->getEntityManager()->flush();

        $view = $this->view(null, 204);

        return $this->handleView($view);
    }

    /**
     * @return ProductFactoryInterface
     */
    private function getProductFactory()
    {
        return $this->get('sulu_product.product_factory');
    }

    /**
     * @return ProductManagerInterface
     */
    private function getProductManager()
    {
        return $this->get('sulu_product.product_manager');
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager()
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    /**
     * @return ProductVariantManagerInterface
     */
    private function getProductVariantManager()
    {
        return $this->get('sulu_product.product_variant_manager');
    }
}
