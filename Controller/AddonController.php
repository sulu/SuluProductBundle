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

use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Product\ProductAddonManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductLocaleManager;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactory;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AddonController extends RestController
{
    protected static $entityName = 'SuluProductBundle:Addon';

    /**
     * @Get("/products/{productId}/addons")
     *
     * @param Request $request
     * @param int $productId
     *
     * @return Response
     */
    public function getProductAddonsAction(Request $request, $productId)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        if ($request->get('flat') == 'true') {
            $fieldDescriptors = $this->getFieldDescriptors($locale);
            $listBuilder = $this->getListBuilder(self::$entityName, $fieldDescriptors);
            $listBuilder->where($fieldDescriptors['productId'], $productId);

            $list = new ListRepresentation(
                $listBuilder->execute(),
                'addons',
                'get_product_addons',
                array_merge($request->query->all(), ['productId' => $productId]),
                $listBuilder->getCurrentPage(),
                $listBuilder->getLimit(),
                $listBuilder->count()
            );
        } else {
            $addons = $this->getManager()->findAddonsByProductIdAndLocale($productId, $locale);
            $list = new CollectionRepresentation(
                $addons,
                'addons'
            );
        }

        $view = $this->view($list, 200);

        return $this->handleView($view);
    }

    /**
     * @Get("/addons/{id}")
     *
     * @param Request $request
     * @param int $id
     *
     * @return Response
     */
    public function getProductAddonAction(Request $request, $id)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));
        $addon = $this->getManager()->findAddonById($id, $locale);

        $view = $this->view($addon, 200);

        return $this->handleView($view);
    }

    /**
     * @Post("/products/{productId}/addons")
     *
     * @param Request $request
     * @param int $productId
     *
     * @return Response
     */
    public function postProductAddonsAction(Request $request, $productId)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        $addonId = $request->get('addon');
        $prices = $request->get('prices');

        $addon = $this->getManager()->saveProductAddon($productId, $addonId, $locale, $prices);

        $this->getDoctrine()->getManager()->flush();

        $view = $this->view($addon, 200);

        return $this->handleView($view);
    }

    /**
     * @Put("/products/{productId}/addons")
     *
     * @param Request $request
     * @param int $productId
     *
     * @return Response
     */
    public function putProductAddonsAction(Request $request, $productId)
    {
        $locale = $this->getProductLocaleManager()->retrieveLocale($this->getUser(), $request->get('locale'));

        $addonId = $request->get('addon');
        $prices = $request->get('prices');

        $addon = $this->getManager()->saveProductAddon($productId, $addonId, $locale, $prices);

        $this->getDoctrine()->getManager()->flush();

        $view = $this->view($addon, 200);

        return $this->handleView($view);
    }

    /**
     * @Delete("/products/{productId}/addons/{addonId}")
     *
     * @param int $productId
     * @param int $addonId
     *
     * @return Response
     */
    public function deleteProductAddonAction($productId, $addonId)
    {
        $this->getManager()->deleteProductAddon($productId, $addonId);
        $this->getDoctrine()->getManager()->flush();

        $view = $this->view();

        return $this->handleView($view);
    }

    /**
     * @Delete("/addons/{id}")
     *
     * @param int $id
     *
     * @return Response
     */
    public function deleteAddonAction($id)
    {
        $this->getManager()->deleteById($id, true);

        $view = $this->view();

        return $this->handleView($view);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function getAddonFieldsAction(Request $request)
    {
        return $this->handleView(
            $this->view(
                array_values($this->getFieldDescriptors($this->getLocale($request), true)),
                200
            )
        );
    }

    /**
     * @param string $locale
     * @param bool $isForOutput
     *
     * @return DoctrineFieldDescriptor[]
     */
    protected function getFieldDescriptors($locale, $isForOutput = false)
    {
        $defaultCurrency = $this->getParameter('sulu_product.default_currency');

        $fieldDescriptors = [];

        $fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            'SuluProductBundle:Addon',
            'public.id',
            [],
            true,
            false,
            'integer'
        );

        $fieldDescriptors['addonName'] = new DoctrineFieldDescriptor(
            'name',
            'addonName',
            'SuluProductBundle:ProductTranslation',
            'products.product-addon',
            [
                'SuluProductBundle:ProductAddon' => new DoctrineJoinDescriptor(
                    'SuluProductBundle:Product',
                    'SuluProductBundle:Addon.addon'
                ),
                'SuluProductBundle:ProductTranslation' => new DoctrineJoinDescriptor(
                    'SuluProductBundle:ProductTranslation',
                    'SuluProductBundle:ProductAddon.translations',
                    'SuluProductBundle:ProductTranslation.locale = \'' . $locale . '\''
                ),
            ],
            false,
            true,
            'string'
        );

        // We dont want to have the productId in the fields action.
        if (!$isForOutput) {
            $fieldDescriptors['productId'] = new DoctrineFieldDescriptor(
                'id',
                'productId',
                'SuluProductBundle:Product',
                'addon.product',
                [
                    'SuluProductBundle:Product' => new DoctrineJoinDescriptor(
                        'SuluProductBundle:Product',
                        'SuluProductBundle:Addon.product'
                    ),
                ],
                false,
                false,
                'integer'
            );
        }

        return $fieldDescriptors;
    }

    /**
     * @return ProductLocaleManager
     */
    protected function getProductLocaleManager()
    {
        return $this->get('sulu_product.product_locale_manager');
    }

    /**
     * @param string $entityName
     * @param DoctrineFieldDescriptor[] $fieldDescriptors
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
     * @return ProductAddonManagerInterface
     */
    protected function getManager()
    {
        return $this->get('sulu_product.product_addons_manager');
    }
}
