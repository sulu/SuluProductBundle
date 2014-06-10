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

use DateTime;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet;
use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;

class ProductController extends RestController implements ClassResourceInterface
{
    protected $entityName = 'SuluProductBundle:Product';
    private $attributeEntityName = 'SuluProductBundle:Attribute';
    private $productTranslationEntityName = 'SuluProductBundle:ProductTranslation';

    /**
     * Returns the language.
     *
     * @param Request $request
     * @return mixed
     */
    private function getLanguage($request)
    {
        $lang = $request->get('language');
        if (!$lang) {
            if ($this->getUser()) {
                $lang = $this->getUser()->getLocale() ? : $this->container->getParameter('locale');
            } else {
                $lang = $this->container->getParameter('locale');
            }
        }
        return $lang;
    }

    /**
     * Returns the repository object for AdvancedProduct
     *
     * @return ProductRepository
     */
    private function getRepository()
    {
        return $this->getDoctrine()->getRepository('SuluProductBundle:Product');
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
        $lang = $this->getLanguage($request);
        $view = $this->responseGetById(
            $id,
            function ($id) use ($lang) {
                /** @var Product $product */
                $product = $this->getRepository()->findByIdAndLanguage($id, $lang);

                return $product;
            }
        );

        return $this->handleView($view);
    }

    /**
     * Returns a list of products
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cgetAction(Request $request)
    {
        if ($request->get('flat') == 'true') {
            $view = $this->responseList();
        } else {
            $parameters = null;
            $query = $request->getQueryString();
            if ($query) {
                list($key, $value) = explode("=", $query);
                $parameters[$key] = $value;
            }
            $parameters['language'] = $this->getLanguage($request);

            $result = $this->getRepository()->findByParameters($parameters);
            $view = $this->view($this->createHalResponse($result), 200);
        }
        return $this->handleView($view);
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
        $lang = $this->getLanguage($request);

        try {
            /** @var Product $product */
            $product = $this->getDoctrine()->getRepository($this->entityName)->findByIdAndLanguage($id, $lang);

            if (!$product) {
                throw new EntityNotFoundException($this->entityName, $id);
            } elseif ($request->get('number') === null) {
                throw new RestException('The <number> property must not be NULL!');
            } else {
                /** @var EntityManager $em */
                $em = $this->getDoctrine()->getManager();

                $product->setCode($request->get('code'));
                $product->setNumber($request->get('number'));
                $product->setManufacturer($request->get('manufacturer'));
                $product->setChanged(new DateTime());

                $parentData = $request->get('parent');
                if ($parentData != null && isset($parentData['id']) && $parentData['id'] != 'null' && $parentData['id'] != '') {
                    $parent = $this->getDoctrine()
                        ->getRepository($this->entityName)
                        ->findByIdAndLanguage($parentData['id'], $lang);

                    if (!$parent) {
                        throw new EntityNotFoundException($this->entityName, $parentData['id']);
                    }
                    $product->setParent($parent);
                } else {
                    $product->setParent(null);
                }

                $em->flush();

                $view = $this->view($product, 200);
            }
        } catch (EntityNotFoundException $ex) {
            $view = $this->view($ex->toArray(), 404);
        } catch (RestException $ex) {
            $view = $this->view($ex->toArray(), 400);
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
            $number = $request->get('number');

            if (!isset($number) || empty($number)) {
                throw new RestException('The <number> property must not be NULL!');
            } else {
                $em = $this->getDoctrine()->getManager();

                $product = new Product();
                $product->setCost($request->get('cost'));
                $product->setPriceInfo($request->get('priceInfo'));

                $product->setCode($request->get('code'));
                $product->setNumber($number);
                $product->setManufacturer($request->get('manufacturer'));
                $product->setManufacturerCountry($request->get('manufacturerCountry'));
                $product->setCreated($request->get('created'));
                $product->setChanged($request->get('changed'));

                $parentId = $request->get('parent');
                if ($parentId) {
                    /** @var Product $parentProduct */
                    $parentProduct = $this->getDoctrine()->getRepository('SuluProductBundle:Product')
                        ->find($parentId);
                    $product->setParent($parentProduct);
                }

                $statusId = $request->get('status');
                if ($statusId) {
                    /** @var Status $status */
                    $status = $this->getDoctrine()->getRepository('SuluProductBundle:Status')->find($statusId);
                    $product->setStatus($status);
                }

                $typeId = $request->get('type');
                if ($typeId) {
                    /** @var Type $type */
                    $type = $this->getDoctrine()->getRepository('SuluProductBundle:Type')->find($typeId);
                    $product->setType($type);
                }

                $attributeSetId = $request->get('attributeSet');
                if ($attributeSetId) {
                    /** @var AttributeSet $attributeSet */
                    $attributeSet = $this->getDoctrine()->getRepository('SuluProductBundle:AttributeSet')
                        ->find($attributeSetId);
                    $product->setAttributeSet($attributeSet);
                }

                $em->persist($product);

                /** @var ProductTranslation $translation */
                $translations = $request->get('translations');
                if (!empty($translations)) {
                    foreach ($translations as $translation) {
                        $this->addProductTranslation($product, $translation);
                    }
                }

                /** @var ProductTranslation $translation */
                $attributes = $request->get('attributes');
                if (!empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $this->addProductAttribute($product, $attribute);
                    }
                }

                $em->flush();
                $view = $this->view($product, 200);
            }
        } catch (RestException $ex) {
            $view = $this->view($ex->toArray(), 400);
        }

        return $this->handleView($view);
    }

    /**
     * Delete an account with the given id.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param integer $id product id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $id)
    {
        $lang = $this->getLanguage($request);

        $delete = function ($id) use ($lang) {
            /* @var Product $product */
            $product = $this->getDoctrine()
                ->getRepository($this->entityName)
                ->findByIdAndLanguage($id, $lang);

            if (!$product) {
                throw new EntityNotFoundException($this->entityName, $id);
            }

            // do not allow to delete entity if child is existent
            if ($product->getChildren()->count() > 0) {
                throw new RestException(400, 'Deletion not allowed, because the product has sub products');
            }

            $em = $this->getDoctrine()->getManager();

            $em->remove($product);
            $em->flush();
        };
        $view = $this->responseDelete($id, $delete);

        return $this->handleView($view);
    }

    /**
     * Adds a translation to the product.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     * @param array $translationData
     * @throws \Sulu\Component\Rest\Exception\EntityIdAlreadySetException
     */
    private function addProductTranslation(ProductInterface $product, array $translationData)
    {
        $em = $this->getDoctrine()->getManager();

        if (isset($translationData['id'])) {
            throw new EntityIdAlreadySetException($this->productTranslationEntityName, $translationData['id']);
        } else {
            $productTranslation = new ProductTranslation();
            $productTranslation->setProduct($product);
            $productTranslation->setLanguageCode($translationData['languageCode']);
            $productTranslation->setName($translationData['name']);
            $productTranslation->setShortDescription($translationData['shortDescription']);
            $productTranslation->setLongDescription($translationData['longDescription']);

            $em->persist($productTranslation);
            $product->addTranslation($productTranslation);
        }
    }

    /**
     * Adds an attribute to the product.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     * @param array $attributeData
     * @throws \Sulu\Component\Rest\Exception\EntityIdAlreadySetException
     * @throws \Sulu\Component\Rest\Exception\RestException
     */
    private function addProductAttribute(ProductInterface $product, array $attributeData)
    {
        $em = $this->getDoctrine()->getManager();

        if (isset($attributeData['id'])) {
            throw new EntityIdAlreadySetException($this->attributeEntityName, $attributeData['id']);
        } elseif (!isset($attributeData['attribute'])) {
            throw new RestException(404, 'Missing attribute property.');
        } else {
            $attribute = $this->getDoctrine()
                ->getRepository($this->attributeEntityName)
                ->find($attributeData['attribute']['id']);

            $productAttribute = new ProductAttribute();
            $productAttribute->setProduct($product);
            $productAttribute->setAttribute($attribute);
            $productAttribute->setValue($attributeData['value']);

            $em->persist($productAttribute);
            $product->addProductAttribute($productAttribute);
        }
    }
}
