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
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductMediaManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductRepositoryInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Exception\FeatureNotImplementedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes setting and removing of media for a product available through a REST API.
 *
 * @RouteResource("Media")
 */
class ProductMediaController extends RestController
{
    /**
     * Returns all fields that can be used by list.
     *
     * @Get("products/media/fields")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function getFieldsAction(Request $request)
    {
        $locale = $request->get('locale');

        return $this->handleView(
            $this->view(
                array_values($this->getManager()->getFieldDescriptors($locale))
            )
        );
    }

    /**
     * Adds a new media to the product.
     *
     * @param Request $request
     * @param int $productId
     *
     * @return Response
     */
    public function postAction(Request $request, $productId)
    {
        $locale = $this->getLocale($request);
        $mediaId = $request->get('mediaId', '');

        try {
            $em = $this->getDoctrine()->getManager();
            /** @var Product $product */
            $product = $this->getProductManager()->findByIdAndLocale($productId, $locale);
            $media = $this->getMediaManager()->getById($mediaId, $locale);

            if (!$product) {
                throw new EntityNotFoundException($this->getProductEntityName(), $productId);
            }

            if (!$media) {
                throw new EntityNotFoundException($this->getMediaEntityName(), $mediaId);
            }

            if ($product->containsMedia($media)) {
                throw new RestException('Relation already exists');
            }

            //FIXME this is just a temporary solution
            // issue https://github.com/massiveart/POOL-ALPIN/issues/1467
            $this->removeDefaultPrices($product);
            $product->addMedia($media);
            $em->flush();

            $view = $this->view($media, 200);
        } catch (EntityNotFoundException $enfe) {
            $view = $this->view($enfe->toArray(), 404);
        } catch (RestException $exc) {
            $view = $this->view($exc->toArray(), 400);
        } catch (\Exception $e) {
            $view = $this->view($e->getMessage(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Updates media of a product.
     *
     * @param Request $request
     * @param int $productId
     *
     * @throws EntityNotFoundException
     * @throws ProductException
     *
     * @return Response
     */
    public function putAction(Request $request, $productId)
    {
        $mediaIds = $request->get('mediaIds');

        if (null === $mediaIds || !is_array($mediaIds)) {
            throw new ProductException('No media ids given.');
        }

        /** @var Product $product */
        $product = $this->getProductRepository()->find($productId);
        if (!$product) {
            throw new EntityNotFoundException($this->getProductEntityName(), $productId);
        }

        $this->getProductMediaManager()->save($product, $mediaIds);
        $this->getEntityManager()->flush();

        $view = $this->view(null, Response::HTTP_NO_CONTENT);

        return $this->handleView($view);
    }

    /**
     * Removes a media from the relation.
     *
     * @param int $productId
     * @param int $mediaId
     *
     * @throws EntityNotFoundException
     * @throws ProductException
     *
     * @return Response
     */
    public function deleteAction($productId, $mediaId)
    {
        /** @var Product $product */
        $product = $this->getProductRepository()->find($productId);
        if (!$product) {
            throw new EntityNotFoundException($this->getProductEntityName(), $productId);
        }

        $this->getProductMediaManager()->delete($product, [$mediaId]);

        $view = $this->view(null, Response::HTTP_NO_CONTENT);
        $this->getEntityManager()->flush();

        return $this->handleView($view);
    }

    /**
     * Lists all media of an account.
     * optional parameter 'flat' calls listAction.
     *
     * @param int $id
     * @param Request $request
     *
     * @throws FeatureNotImplementedException
     *
     * @return Response
     */
    public function cgetAction($id, Request $request)
    {
        try {
            if ($request->get('flat') === 'true') {
                $list = $this->getListRepresentation($id, $request);
            } else {
                throw new FeatureNotImplementedException('');
            }
            $view = $this->view($list, 200);
        } catch (EntityNotFoundException $e) {
            $view = $this->view($e->toArray(), 404);
        }

        return $this->handleView($view);
    }

    /**
     * Returns a list representation.
     *
     * @param int $id
     * @param Request $request
     *
     * @return ListRepresentation
     */
    protected function getListRepresentation($id, $request)
    {
        $locale = $request->get('locale');

        /** @var RestHelperInterface $restHelper */
        $restHelper = $this->get('sulu_core.doctrine_rest_helper');

        /** @var DoctrineListBuilderFactory $factory */
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');

        $listBuilder = $factory->create($this->container->getParameter('sulu_product.product_entity'));
        $fieldDescriptors = $this->getManager()->getFieldDescriptors($locale);
        $listBuilder->where($fieldDescriptors['product'], $id);

        $restHelper->initializeListBuilder(
            $listBuilder,
            $fieldDescriptors
        );

        $listResponse = $listBuilder->execute();
        $listResponse = $this->addThumbnails($listResponse, $locale);

        $list = new ListRepresentation(
            $listResponse,
            'media',
            'cget_product_media',
            array_merge(['id' => $id], $request->query->all()),
            $listBuilder->getCurrentPage(),
            $listBuilder->getLimit(),
            $listBuilder->count()
        );

        return $list;
    }

    /**
     * Takes an array of entities and resets the thumbnails-property containing the media id with
     * the actual urls to the thumbnails.
     *
     * @param array $entities
     * @param string $locale
     *
     * @return array
     */
    protected function addThumbnails($entities, $locale)
    {
        $ids = array_filter(array_column($entities, 'thumbnails'));
        $thumbnails = $this->getMediaManager()->getFormatUrls($ids, $locale);
        foreach ($entities as $key => $entity) {
            if (array_key_exists('thumbnails', $entity)
                && $entity['thumbnails']
                && array_key_exists($entity['thumbnails'], $thumbnails)
            ) {
                $entities[$key]['thumbnails'] = $thumbnails[$entity['thumbnails']];
            }
        }

        return $entities;
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager()
    {
        return $this->get('doctrine.orm.entity_manager');
    }

    /**
     * Returns the product media manager.
     *
     * @return ProductMediaManagerInterface
     */
    protected function getManager()
    {
        return $this->get('sulu_product.product_media_manager');
    }

    /**
     * Returns the product manager.
     *
     * @return ProductManagerInterface
     */
    protected function getProductManager()
    {
        return $this->get('sulu_product.product_manager');
    }

    /**
     * Returns the product repository.
     *
     * @return ProductRepositoryInterface
     */
    protected function getProductRepository()
    {
        return $this->get('sulu_product.product_repository');
    }

    /**
     * Returns the media repository.
     *
     * @return MediaRepositoryInterface
     */
    protected function getMediaRepository()
    {
        return $this->get('sulu.repository.media');
    }

    /**
     * Returns the product media manager.
     *
     * @return ProductMediaManagerInterface
     */
    protected function getProductMediaManager()
    {
        return $this->get('sulu_product.product_media_manager');
    }

    /**
     * Returns the media manager.
     *
     * @return MediaManagerInterface
     */
    protected function getMediaManager()
    {
        return $this->get('sulu_media.media_manager');
    }

    /**
     * @return string
     */
    protected function getMediaEntityName()
    {
        return $this->getMediaEntityName()->getClassName();
    }

    /**
     * @return string
     */
    protected function getProductEntityName()
    {
        return $this->getProductRepository()->getClassName();
    }

    /**
     * Removes default prices from product.
     *
     * @param Product $product
     */
    private function removeDefaultPrices(Product $product)
    {
        $defaultPrices = [];

        // get default prices
        foreach ($product->getPrices() as $price) {
            if ($price->getId() === null) {
                $defaultPrices[] = $price;
            }
        }

        foreach ($defaultPrices as $price) {
            $product->removePrice($price->getEntity());
        }
    }
}
