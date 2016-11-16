<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product\Mapper;

use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductRouteManagerInterface;
use Sulu\Bundle\ProductBundle\Traits\ArrayDataTrait;

/**
 * This service is responsible for managing data to product content.
 */
class ProductContentMapper implements ProductContentMapperInterface
{
    use ArrayDataTrait;

    /**
     * @var ProductManagerInterface
     */
    private $productManager;

    /**
     * @var ProductRouteManagerInterface
     */
    private $productRouteManager;

    /**
     * @param ProductManagerInterface $productManager
     * @param ProductRouteManagerInterface $productRouteManager
     */
    public function __construct(
        ProductManagerInterface $productManager,
        ProductRouteManagerInterface $productRouteManager
    ) {
        $this->productManager = $productManager;
        $this->productRouteManager = $productRouteManager;
    }

    /**
     * {@inheritdoc}
     */
    public function map(ProductInterface $product, array $data, $locale)
    {
        $productTranslation = $this->productManager->retrieveOrCreateProductTranslationByLocale($product, $locale);

        // Update route on product translation.
        $this->productRouteManager->saveRoute($productTranslation, $this->getProperty($data, 'routePath'));

        // Set content title.
        $productTranslation->setContentTitle($this->getProperty($data, 'title'));

        return $this->parseContentToArray($productTranslation);
    }

    /**
     * {@inheritdoc}
     */
    public function get(ProductInterface $product, $locale)
    {
        $productTranslation = $this->productManager->retrieveOrCreateProductTranslationByLocale($product, $locale);

        return $this->parseContentToArray($productTranslation);
    }

    /**
     * Parses product content data to an array.
     *
     * @param ProductTranslation $productTranslation
     *
     * @return array
     */
    private function parseContentToArray(ProductTranslation $productTranslation)
    {
        $routePath = null;
        if ($productTranslation->getRoute()) {
            $routePath = $productTranslation->getRoute()->getPath();
        }

        return [
            'title' => $productTranslation->getContentTitle(),
            'routePath' => $routePath,
        ];
    }
}
