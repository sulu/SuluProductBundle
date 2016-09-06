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

use Sulu\Bundle\ProductBundle\Api\Status;
use Sulu\Bundle\ProductBundle\Api\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Product\ProductManager;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends RestController
{
    /**
     * Returns template for product list.
     *
     * @return Response
     */
    public function productListAction()
    {
        return $this->render('SuluProductBundle:Template:product.list.html.twig');
    }

    /**
     * Returns template for product list.
     *
     * @return Response
     */
    public function productFormAction()
    {
        $userLocale = $this->getUser()->getLocale();

        $status = $this->getStatus($userLocale);
        $units = $this->getUnits($userLocale);
        $deliveryStates = $this->getDeliveryStates($userLocale);

        return $this->render(
            'SuluProductBundle:Template:product.form.html.twig',
            [
                'MAX_SEARCH_TERMS_LENGTH' => ProductManager::MAX_SEARCH_TERMS_LENGTH,
                'status' => $status,
                'units' => $units,
                'deliveryStates' => $deliveryStates,
                'categoryUrl' => $this->getCategoryUrl(),
            ]
        );
    }

    /**
     * Returns template for product variants.
     *
     * @return Response
     */
    public function productVariantsAction()
    {
        return $this->render('SuluProductBundle:Template:product.variants.html.twig');
    }

    /**
     * Returns template for product import.
     *
     * @return Response
     */
    public function productImportAction()
    {
        return $this->render(
            'SuluProductBundle:Template:product.import.html.twig'
        );
    }

    /**
     * Returns template for attribute list.
     *
     * @return Response
     */
    public function attributeListAction()
    {
        return $this->render(
            'SuluProductBundle:Template:attribute.list.html.twig'
        );
    }

    /**
     * Returns template for attribute list.
     *
     * @return Response
     */
    public function attributeFormAction()
    {
        $repository = $this->getDoctrine()
            ->getRepository('SuluProductBundle:AttributeType');
        $types = $repository->findAll();

        $attributeTypes = [];
        foreach ($types as $type) {
            $attributeTypes[] = [
                'id' => $type->getId(),
                'name' => $type->getName(),
            ];
        }

        return $this->render(
            'SuluProductBundle:Template:attribute.form.html.twig',
            [
                'attribute_types' => $attributeTypes,
            ]
        );
    }

    /**
     * Returns template for product pricing.
     *
     * @return Response
     */
    public function productPricingAction()
    {
        $userLocale = $this->getUser()->getLocale();

        /** @var TaxClass[] $taxClasses */
        $taxClasses = $this->get('sulu_product.tax_class_manager')->findAll($userLocale);

        $taxClassTitles = [];
        foreach ($taxClasses as $taxClass) {
            $taxClassTitles[] = [
                'id' => $taxClass->getId(),
                'name' => $taxClass->getName(),
            ];
        }

        $currencies = $this->getCurrencies($userLocale);
        $defaultCurrency = $this->container->getParameter('sulu_product.default_currency');
        $displayRecurringPrices = $this->container->getParameter('sulu_product.display_recurring_prices');

        return $this->render(
            'SuluProductBundle:Template:product.pricing.html.twig',
            [
                'taxClasses' => $taxClassTitles,
                'currencies' => $currencies,
                'defaultCurrency' => $defaultCurrency,
                'displayRecurringPrices' => $displayRecurringPrices,
            ]
        );
    }

    /**
     * Returns the template for product documents.
     *
     * @return Response
     */
    public function productDocumentsAction()
    {
        return $this->render('SuluProductBundle:Template:product.documents.html.twig');
    }

    /**
     * Returns the template for product attributes.
     *
     * @return Response
     */
    public function productAttributesAction()
    {
        return $this->render('SuluProductBundle:Template:product.attributes.html.twig');
    }

    /**
     * Returns the template for product addons.
     *
     * @return Response
     */
    public function productAddonsAction()
    {
        return $this->render('SuluProductBundle:Template:product.addons.html.twig');
    }

    /**
     * Returns status for products.
     *
     * @param string $locale
     *
     * @return array
     */
    protected function getStatus($locale)
    {
        /** @var Status[] $statuses */
        $statuses = $this->get('sulu_product.status_manager')->findAll($locale);

        $statusTitles = [];
        foreach ($statuses as $status) {
            $statusTitles[] = [
                'id' => $status->getId(),
                'name' => $status->getName(),
            ];
        }

        return $statusTitles;
    }

    /**
     * Returns units.
     *
     * @param string $locale
     *
     * @return array
     */
    protected function getUnits($locale)
    {
        /** @var Status[] $units */
        $units = $this->get('sulu_product.unit_manager')->findAll($locale);

        $unitTitles = [];
        foreach ($units as $unit) {
            $unitTitles[] = [
                'id' => $unit->getId(),
                'name' => $unit->getName(),
            ];
        }

        return $unitTitles;
    }

    /**
     * Returns currencies.
     *
     * @param string $locale
     *
     * @return array
     */
    protected function getCurrencies($locale)
    {
        /** @var Currency[] $currencies */
        $currencies = $this->get('sulu_product.currency_manager')->findAll($locale);

        $currencyTitles = [];
        foreach ($currencies as $currency) {
            $currencyTitles[] = [
                'id' => $currency->getId(),
                'name' => $currency->getName(),
                'code' => $currency->getCode(),
                'number' => $currency->getNumber(),
            ];
        }

        return $currencyTitles;
    }

    /**
     * Returns url for fetching categories.
     *
     * If sulu_product.category_root_key is specified only categories of this specific root key
     * are going to be fetched. Otherwise the whole category tree is returned.
     *
     * @return string
     */
    protected function getCategoryUrl()
    {
        $rootKey = $this->container->getParameter('sulu_product.category_root_key');

        if (null !== $rootKey) {
            return $this->generateUrl(
                'get_category_children',
                ['key' => $rootKey, 'flat' => 'true', 'sortBy' => 'depth', 'sortOrder' => 'asc']
            );
        }

        return $this->generateUrl(
            'get_categories',
            ['flat' => 'true', 'sortBy' => 'depth', 'sortOrder' => 'asc']
        );
    }

    /**
     * Returns delivery states.
     *
     * @param string $locale
     *
     * @return array
     */
    protected function getDeliveryStates($locale)
    {
        $states = $this->getDeliveryStatusManager()->findAll($locale);

        $deliveryStates = [];
        foreach ($states as $state) {
            $deliveryStates[] = [
                'id' => $state->getId(),
                'name' => $state->getName(),
            ];
        }

        return $deliveryStates;
    }

    /**
     * Returns the delivery status manager.
     *
     * @return DeliveryStatusManager
     */
    private function getDeliveryStatusManager()
    {
        return $this->get('sulu_product.delivery_status_manager');
    }
}
