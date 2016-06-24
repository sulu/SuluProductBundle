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

use FOS\RestBundle\Controller\Annotations\Get;
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

        $fieldDescriptors['name'] = new DoctrineFieldDescriptor(
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

        $fieldDescriptors['prices'] = new DoctrineFieldDescriptor(
            'price',
            'price',
            'SuluProductBundle:AddonPrice',
            'product.price',
            [
                'SuluProductBundle:AddonPrice' => new DoctrineJoinDescriptor(
                    'SuluProductBundle:AddonPrice',
                    'SuluProductBundle:Addon.addonPrices'
                )
            ],
            false,
            true,
            'string'
        );

        $fieldDescriptors['currency'] = new DoctrineFieldDescriptor(
            'name',
            'currencyName',
            'SuluProductBundle:Currency',
            'product.currency',
            [
                'SuluProductBundle:AddonPrice' => new DoctrineJoinDescriptor(
                    'SuluProductBundle:AddonPrice',
                    'SuluProductBundle:Addon.addonPrices'
                ),
                'SuluProductBundle:Currency' => new DoctrineJoinDescriptor(
                    'SuluProductBundle:Currency',
                    'SuluProductBundle:AddonPrice.currency',
                    'SuluProductBundle:Currency.code = \'' . $defaultCurrency . '\'',
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
            ],
            false,
            false,
            'string'
        );

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
