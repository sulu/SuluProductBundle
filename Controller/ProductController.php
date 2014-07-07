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

use Doctrine\ORM\EntityManager;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\HateoasBuilder;
use Hateoas\Representation\CollectionRepresentation;
use Hateoas\Representation\PaginatedRepresentation;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Entity\Product as ProductEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Rest\Exception\EntityIdAlreadySetException;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\Exception\RestException;
use Sulu\Component\Rest\ListBuilder\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\DoctrineListBuilderFactory;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\Get;

class ProductController extends RestController implements ClassResourceInterface
{
    protected $entityName = 'SuluProductBundle:Product';
    private $attributeEntityName = 'SuluProductBundle:Attribute';
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
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        /** @var DoctrineListBuilderFactory $factory */
        $factory = $this->get('sulu_core.doctrine_list_builder_factory');
        $products = $factory->create($this->entityName)
            ->limit($limit)
            ->setCurrentPage($page)
            ->execute();

        array_walk(
            $products,
            function (&$product) use ($request) {
                $product = new Product($product, $this->getLocale($request));
            }
        );

        $collection = new PaginatedRepresentation(
            new CollectionRepresentation($products),
            'get_products',
            array(),
            $page,
            $limit,
            3
        );

        $view = $this->view($collection, 200);
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
        $locale = $this->getLocale($request);

        try {
            $product = $this->getManager()->findByIdAndLocale($id, $locale);

            if (!$product) {
                throw new EntityNotFoundException($this->entityName, $id);
            } elseif ($request->get('number') === null) {
                throw new RestException('The number property must not be NULL!');
            } else {
                /** @var EntityManager $em */
                $this->fillProductFromRequest($request, $product);

                $product = $this->getManager()->save($product, $this->getUser()->getId());

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
            $locale = $this->getLocale($request);
            $number = $request->get('number');
            $typeId = $request->get('type')['id'];
            $statusId = $request->get('status')['id'];

            if ($number == null) {
                throw new RestException('There is no number for the product given');
            }

            if ($typeId == null) {
                throw new RestException('There is no type for the product given');
            }

            if ($statusId == null) {
                throw new RestException('There is no status for the product given');
            }

            $product = new Product(new ProductEntity(), $locale);

            $this->fillProductFromRequest($request, $product);

            $product = $this->getManager()->save($product, $this->getUser()->getId());

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
        $locale = $this->getLocale($request);

        $delete = function ($id) use ($locale) {
            $product = $this->getManager()->findByIdAndLocale($id, $locale);

            if (!$product) {
                throw new EntityNotFoundException($this->entityName, $id);
            }

            // do not allow to delete entity if child is existent
            if (count($product->getChildren()) > 0) {
                throw new RestException(400, 'Deletion not allowed, because the product has sub products');
            }

            $this->getManager()->delete($product, $this->getUser()->getId());
        };
        $view = $this->responseDelete($id, $delete);

        return $this->handleView($view);
    }

    /**
     * @param Request $request
     * @param Product $product
     * @throws \Sulu\Component\Rest\Exception\EntityNotFoundException
     */
    private function fillProductFromRequest(Request $request, Product $product)
    {
        $product->setName($request->get('name'));
        $product->setShortDescription($request->get('shortDescription'));
        $product->setLongDescription($request->get('longDescription'));
        $product->setCode($request->get('code'));
        $product->setNumber($request->get('number'));
        $product->setManufacturer($request->get('manufacturer'));

        $product->setCost($request->get('cost'));
        $product->setPriceInfo($request->get('priceInfo'));

        $product->setCode($request->get('code'));
        $product->setNumber($request->get('number'));
        $product->setManufacturer($request->get('manufacturer'));

        $attributeSetId = $request->get('attributeSet')['id'];
        if ($attributeSetId) {
            /** @var AttributeSet $attributeSet */
            $attributeSet = $this->getDoctrine()->getRepository($this->attributeSetEntityName)
                ->find($attributeSetId);
            if (!$attributeSet) {
                throw new EntityNotFoundException($this->attributeSetEntityName, $attributeSetId);
            }
            $product->setAttributeSet($attributeSet);
        }

        $parentId = $request->get('parent')['id'];
        if ($parentId) {
            /** @var ProductEntity $parentProduct */
            $parentProduct = $this->getDoctrine()->getRepository($this->entityName)->find($parentId);
            if (!$parentProduct) {
                throw new EntityNotFoundException($this->entityName, $parentId);
            }
            $product->setParent($parentProduct);
        } else {
            $product->setParent(null);
        }

        $statusId = $request->get('status')['id'];
        if ($statusId) {
            /** @var Status $status */
            $status = $this->getDoctrine()->getRepository($this->productStatusEntityName)->find($statusId);
            if (!$status) {
                throw new EntityNotFoundException($this->productStatusEntityName, $statusId);
            }
            $product->setStatus($status);
        }

        $typeId = $request->get('type')['id'];
        if ($typeId) {
            /** @var Type $type */
            $type = $this->getDoctrine()->getRepository($this->productTypeEntityName)->find($typeId);
            if (!$type) {
                throw new EntityNotFoundException($this->productTypeEntityName, $typeId);
            }
            $product->setType($type);
        }

//        $product->setManufacturerCountry($request->get('manufacturerCountry'));
    }
}
