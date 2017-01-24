<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Event;

use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event class for product-translation.
 */
class ProductTranslationEvent extends Event
{
    /**
     * @var ProductTranslation
     */
    private $productTranslation;

    /**
     * @param ProductTranslation $productTranslation
     */
    public function __construct(ProductTranslation $productTranslation)
    {
        $this->productTranslation = $productTranslation;
    }

    /**
     * @return ProductTranslation
     */
    public function getProductTranslation()
    {
        return $this->productTranslation;
    }
}
