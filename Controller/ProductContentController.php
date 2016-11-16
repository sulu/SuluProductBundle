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
use FOS\RestBundle\Controller\Annotations\RouteResource;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Product\Mapper\ProductContentMapperInterface;
use Sulu\Bundle\ProductBundle\Product\ProductRepositoryInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes content of a product available through a REST API.
 *
 * @RouteResource("Content")
 */
class ProductContentController extends RestController
{
    /**
     * Action for receiving contents of a given product.
     *
     * @param Request $request
     * @param int $productId
     *
     * @return Response
     */
    public function getAction(Request $request, $productId)
    {
        $locale = $request->get('locale');
        $product = $this->fetchProduct($productId);

        $content = $this->getProductContentMapper()->get($product, $locale);

        $view = $this->view($content, Response::HTTP_OK);

        return $this->handleView($view);
    }

    /**
     * Put action to update content of a product.
     *
     * @param Request $request
     * @param int $productId
     *
     * @throws EntityNotFoundException
     *
     * @return Response
     */
    public function putAction(Request $request, $productId)
    {
        $locale = $request->get('locale');
        $parameters = $request->request->all();

        $product = $this->fetchProduct($productId);

        $content = $this->getProductContentMapper()->map($product, $parameters, $locale);
        $this->getEntityManager()->flush();

        $view = $this->view($content, Response::HTTP_OK);

        return $this->handleView($view);
    }

    /**
     * Fetches product by id from database.
     *
     * @param int $productId
     *
     * @throws EntityNotFoundException
     *
     * @return ProductInterface
     */
    public function fetchProduct($productId)
    {
        /** @var ProductInterface $product */
        $product = $this->getProductRepository()->find($productId);
        if (!$product) {
            throw new EntityNotFoundException($this->getProductRepository()->getClassName(), $productId);
        }

        return $product;
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager()
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    /**
     * @return ProductContentMapperInterface
     */
    private function getProductContentMapper()
    {
        return $this->get('sulu_product.product_content_mapper');
    }

    /**
     * @return ProductRepositoryInterface
     */
    private function getProductRepository()
    {
        return $this->get('sulu.repository.product');
    }
}
