<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ProductBundle\Event\ProductTranslationEvent;
use Sulu\Bundle\ProductBundle\Product\ProductRouteManagerInterface;

/**
 * Event listener for product translation events.
 */
class ProductTranslationEventListener
{
    /**
     * @var ProductRouteManagerInterface
     */
    private $productRouteManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param ProductRouteManagerInterface $productRouteManager
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ProductRouteManagerInterface $productRouteManager,
        EntityManagerInterface $entityManager
    ) {
        $this->productRouteManager = $productRouteManager;
        $this->entityManager = $entityManager;
    }

    /**
     * Called when product translation has been created and stored to database.
     * Will save a new product route.
     *
     * @param ProductTranslationEvent $productTranslationEvent
     */
    public function postPersist(ProductTranslationEvent $productTranslationEvent)
    {
        $this->productRouteManager->saveRoute($productTranslationEvent->getProductTranslation());
        $this->entityManager->flush();
    }
}
