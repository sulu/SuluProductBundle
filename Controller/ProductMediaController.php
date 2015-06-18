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

use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\RouteResource;

/**
 * Makes setting and removing of media for a product available through a REST API
 * @RouteResource("Media")
 * @package Sulu\Bundle\ProductBundle\Controller
 */
class ProductMediaController extends RestController
{
    protected static $mediaEntityName = 'SuluMediaBundle:Media';
    protected static $productEntityName = 'SuluProductBundle:Product';

    private $mediaManager;
    private $productManager;

    /**
     * Returns the product manager
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
     * Returns the media manager
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
     * Adds a new media to the account
     *
     * @param $id - the product id
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
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
     * Removes default prices from product
     * @param Product $product
     */
    private function removeDefaultPrices(Product $product) {
        $defaultPrices = [];

        // get default prices
        foreach($product->getPrices() as $price) {
            if($price->getId() === null){
                $defaultPrices[] = $price;
            }
        }

        foreach($defaultPrices as $price){
            $product->removePrice($price->getEntity());
        }
    }

    /**
     * Removes a media from the relation to the account
     *
     * @param $id - account id
     * @param $mediaId
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
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
}
