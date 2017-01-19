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

use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller is used for viewing product-templates.
 */
class WebsiteProductController extends Controller
{
    /**
     * This action is used for displaying a product via a template.
     * The template that is defined by sulu_product.template parameter is used for displaying
     * the product.
     *
     * @param ProductInterface $product
     * @param ProductTranslation $translation
     *
     * @return Response
     */
    public function indexAction(ProductInterface $product, ProductTranslation $translation)
    {
        $apiProduct = $this->getProductFactory()->createApiEntity($product, $translation->getLocale());

        return $this->render(
            $this->getProductViewTemplate(),
            [
                'product' => $apiProduct,
                'urls' => $this->getAllRoutesOfProduct($product),
            ]
        );
    }

    /**
     * Returns all routes that are defined for given product.
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getAllRoutesOfProduct(ProductInterface $product)
    {
        $urls = [];
        /** @var ProductTranslation $productTranslation */
        foreach ($product->getTranslations() as $productTranslation) {
            if ($productTranslation->getRoute()) {
                $urls[$productTranslation->getLocale()] = $productTranslation->getRoute()->getPath();
            }
        }

        return $urls;
    }

    /**
     * @return ProductFactoryInterface
     */
    private function getProductFactory()
    {
        return $this->get('sulu_product.product_factory');
    }

    /**
     * @return string
     */
    private function getProductViewTemplate()
    {
        return $this->getParameter('sulu_product.template');
    }
}
