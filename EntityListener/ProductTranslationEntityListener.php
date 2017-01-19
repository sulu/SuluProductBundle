<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\EntityListener;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Event\Events;
use Sulu\Bundle\ProductBundle\Event\ProductTranslationEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Entity listener for product translation class.
 */
class ProductTranslationEntityListener
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     *
     * @param ProductTranslation $productTranslation
     * @param LifecycleEventArgs $event
     */
    public function postPersist(ProductTranslation $productTranslation)
    {
        $this->eventDispatcher->dispatch(
            Events::PRODUCT_TRANSLATION_CREATED,
            new ProductTranslationEvent($productTranslation)
        );
    }
}
