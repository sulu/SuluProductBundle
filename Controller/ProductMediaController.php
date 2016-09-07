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

use FOS\RestBundle\Controller\Annotations\RouteResource;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductMediaManagerInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Exception\FeatureNotImplementedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// TODO Refactor: use manager for product-media

/**
 * Makes setting and removing of media for a product available through a REST API.
 *
 * @RouteResource("Media")
 */
class ProductMediaController extends RestController
{
    protected static $mediaEntityName = 'SuluMediaBundle:Media';
    protected static $productEntityName = 'SuluProductBundle:Product';

    private $mediaManager;
    private $productManager;

    /**
     * Returns the product manager.
     *
     * @return ProductManagerInterface
     */
    protected function getProductManager()
    {
        if (!$this->productManager) {
            $this->productManager = $this->get('sulu_product.product_manager');
        }

        return $this->productManager;
    }

    /**
     * Returns the media manager.
     *
     * @return MediaManagerInterface
     */
    protected function getMediaManager()
    {
        if (!$this->mediaManager) {
            $this->mediaManager = $this->get('sulu_media.media_manager');
        }

        return $this->mediaManager;
    }

    /**
     * Adds a new media to the account.
     *
     * @param int $id - the product id
     * @param Request $request
     *
     * @return Response
     */
    public function postAction($id, Request $request)
    {
        $locale = $this->getLocale($request);
        $mediaId = $request->get('mediaId', '');

        try {
            $em = $this->getDoctrine()->getManager();
            /** @var Product $product */
            $product = $this->getProductManager()->findByIdAndLocale($id, $locale);
            $media = $this->getMediaManager()->getById($mediaId, $locale);

            if (!$product) {
                throw new EntityNotFoundException(self::$productEntityName, $id);
            }

            if (!$media) {
                throw new EntityNotFoundException(self::$mediaEntityName, $mediaId);
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

    /**
     * Removes a media from the relation.
     *
     * @param int $id - account id
     * @param int $mediaId
     * @param Request $request
     *
     * @return Response
     */
    public function deleteAction($id, $mediaId, Request $request)
    {
        $locale = $this->getLocale($request);

        try {
            $delete = function () use ($id, $mediaId, $locale) {
                $em = $this->getDoctrine()->getManager();

                /** @var Product $product */
                $product = $this->getProductManager()->findByIdAndLocale($id, $locale);
                $media = $this->getMediaManager()->getById($mediaId, $locale);

                if (!$product) {
                    throw new EntityNotFoundException(self::$productEntityName, $id);
                }

                if (!$media) {
                    throw new EntityNotFoundException(self::$mediaEntityName, $mediaId);
                }

                if (!$product->containsMedia($media)) {
                    throw new RestException(
                        'Relation between ' . self::$productEntityName .
                        ' and ' . self::$mediaEntityName . ' with id ' . $mediaId . ' does not exists!'
                    );
                }

                //FIXME this is just a temporary solution
                // issue https://github.com/massiveart/POOL-ALPIN/issues/1467
                $this->removeDefaultPrices($product);
                $product->removeMedia($media);
                $em->flush();
            };

            $view = $this->responseDelete($id, $delete);
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
        $locale = $this->getUser()->getLocale();

        /** @var RestHelperInterface $restHelper */
        $restHelper = $this->get('sulu_core.doctrine_rest_helper');

        /** @var DoctrineListBuilderFactory $factory */
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');

        $listBuilder = $factory->create($this->container->getParameter('sulu_product.product_entity'));
        $fieldDescriptors = $this->getManager()->getFieldDescriptors();
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
     * Returns all fields that can be used by list.
     *
     * @return Response
     */
    public function fieldsAction()
    {
        return $this->handleView(
            $this->view(
                array_values($this->getManager()->getFieldDescriptors())
            )
        );
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
}
