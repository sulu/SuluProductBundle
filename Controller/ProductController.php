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
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\Get;

class ProductController extends RestController implements ClassResourceInterface
{
    protected $entityName = 'SuluProductBundle:Product';
    private $attributeEntityName = 'SuluProductBundle:Attribute';
    private $productTranslationEntityName = 'SuluProductBundle:ProductTranslation';
    protected $productTypeEntityName = 'SuluProductBundle:Type';
    protected $productStatusEntityName = 'SuluProductBundle:Status';
    protected $attributeSetEntityName = 'SuluProductBundle:AttributeSet';

    /**
     * Returns the language.
     *
     * @param Request $request
     * @return mixed
     */
    private function getLocale($request)
    {
        $lang = $request->get('locale');
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
     * @return ProductManagerInterface
     */
    private function getManager()
    {
        return $this->get('sulu_product.product_manager');
    }

    /**
     * returns all fields that can be used by list
     * @Get("products/fields")
     * @return mixed
     */
    public function getFieldsAction()
    {
        return $this->responseFields();
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
     * Returns a list of products
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cgetAction(Request $request)
    {
        $locale = $this->getLocale($request);

        $filter = array(
            'code' => $request->get('code'),
            'number' => $request->get('number'),
            'status' => $request->get('status'),
            'type' => $request->get('type')
        );

        $filter = array_filter($filter);

        $result = $this->getManager()->findAllByLocale($locale, $filter);
        $view = $this->view($this->createHalResponse($result), 200);
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
        $lang = $this->getLocale($request);
        $now = new DateTime();

        try {
            /** @var Product $product */
            $product = $this->getDoctrine()->getRepository($this->entityName)->findById($id, $lang);

            if (!$product) {
                throw new EntityNotFoundException($this->entityName, $id);
            } elseif ($request->get('number') === null) {
                throw new RestException('The number property must not be NULL!');
            } else {
                /** @var EntityManager $em */
                $em = $this->getDoctrine()->getManager();

                $product->setCode($request->get('code'));
                $product->setNumber($request->get('number'));
                $product->setManufacturer($request->get('manufacturer'));
                $product->setChanged($now);
                $product->setChanger($this->getUser());

                $this->setParent($product, $request->get('parent'));
                $this->setStatus($product, $request->get('status'));
                $this->setAttributeSet($product, $request->get('attributeSet'));
                $this->setType($product, $request->get('type'));

                $em->flush();

                $view = $this->view($product, 200);
            }
        } catch (EntityNotFoundException $ex) {
            if ($ex->getId() == $id) {
                $view = $this->view($ex->toArray(), 404);
            } else {
                $view = $this->view($ex->toArray(), 400);
            }
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
            $typeId = $request->get('type');
            $statusId = $request->get('status');
            $now = new DateTime();

            if ($number == null) {
                throw new RestException('There is no number for the product given');
            }

            if ($typeId == null) {
                throw new RestException('There is no type for the product given');
            }

            if ($statusId == null) {
                throw new RestException('There is no status for the product given');
            }

            $em = $this->getDoctrine()->getManager();

            $product = new Product();
            $product->setCost($request->get('cost'));
            $product->setPriceInfo($request->get('priceInfo'));

            $product->setCode($request->get('code'));
            $product->setNumber($number);
            $product->setManufacturer($request->get('manufacturer'));
            $product->setManufacturerCountry($request->get('manufacturerCountry'));
            $product->setCreated($now);
            $product->setChanged($now);
            $product->setCreator($this->getUser());
            $product->setChanger($this->getUser());

            $this->setParent($product, $request->get('parent'));
            $this->setStatus($product, $statusId);
            $this->setType($product, $typeId);
            $this->setAttributeSet($product, $request->get('attributeSet'));

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
        $lang = $this->getLocale($request);

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
            $productTranslation->setLocale($translationData['languageCode']);
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

    /**
     * @param Product $product
     * @param string $attributeSetId
     * @throws \Sulu\Component\Rest\Exception\EntityNotFoundException
     */
    private function setAttributeSet(Product $product, $attributeSetId)
    {
        if ($attributeSetId) {
            /** @var AttributeSet $attributeSet */
            $attributeSet = $this->getDoctrine()->getRepository($this->attributeSetEntityName)
                ->find($attributeSetId);
            if (!$attributeSet) {
                throw new EntityNotFoundException($this->attributeSetEntityName, $attributeSetId);
            }
            $product->setAttributeSet($attributeSet);
        }
    }

    /**
     * @param Product $product
     * @param string $parentId
     * @throws \Sulu\Component\Rest\Exception\EntityNotFoundException
     */
    private function setParent(Product $product, $parentId)
    {
        if ($parentId) {
            /** @var Product $parentProduct */
            $parentProduct = $this->getDoctrine()->getRepository($this->entityName)->find($parentId);
            if (!$parentProduct) {
                throw new EntityNotFoundException($this->entityName, $parentId);
            }
            $product->setParent($parentProduct);
        } else {
            $product->setParent(null);
        }
    }

    /**
     * @param Product $product
     * @param string $statusId
     * @throws \Sulu\Component\Rest\Exception\EntityNotFoundException
     */
    private function setStatus(Product $product, $statusId)
    {
        if ($statusId) {
            /** @var Status $status */
            $status = $this->getDoctrine()->getRepository($this->productStatusEntityName)->find($statusId);
            if (!$status) {
                throw new EntityNotFoundException($this->productStatusEntityName, $statusId);
            }
            $product->setStatus($status);
        }
    }

    /**
     * @param Product $product
     * @param string $typeId
     * @throws \Sulu\Component\Rest\Exception\EntityNotFoundException
     */
    private function setType(Product $product, $typeId)
    {
        if ($typeId) {
            /** @var Type $type */
            $type = $this->getDoctrine()->getRepository($this->productTypeEntityName)->find($typeId);
            if (!$type) {
                throw new EntityNotFoundException($this->productTypeEntityName, $typeId);
            }
            $product->setType($type);
        }
    }
}
