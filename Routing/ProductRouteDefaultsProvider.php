<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Routing;

use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslationRepository;
use Sulu\Bundle\RouteBundle\Routing\Defaults\RouteDefaultsProviderInterface;

/**
 * Provides route-defaults for products.
 */
class ProductRouteDefaultsProvider implements RouteDefaultsProviderInterface
{
    /**
     * @var ProductTranslationRepository
     */
    private $productTranslationRepository;

    /**
     * @param ProductTranslationRepository $productTranslationRepository
     */
    public function __construct(ProductTranslationRepository $productTranslationRepository)
    {
        $this->productTranslationRepository = $productTranslationRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntity($entityClass, $id, $locale, $object = null)
    {
        if (!$object) {
            $object = $this->productTranslationRepository->findOneBy(
                [
                    'id' => $id,
                    'locale' => $locale,
                ]
            );
        }

        return [
            'product' => $object->getProduct(),
            'object' => $object,
            '_controller' => 'SuluProductBundle:WebsiteProduct:index',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isPublished($entityClass, $id, $locale)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($entityClass)
    {
        return $entityClass === ProductTranslation::class;
    }
}
