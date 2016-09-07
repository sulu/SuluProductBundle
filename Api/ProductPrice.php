<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice as Entity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The Status class which will be exported to the API.
 *
 * @ExclusionPolicy("all")
 */
class ProductPrice extends ApiWrapper
{
    /**
     * @param Entity $type
     * @param string $locale
     */
    public function __construct(Entity $taxClass, $locale)
    {
        $this->entity = $taxClass;
        $this->locale = $locale;
    }

    /**
     * Returns the ID of the ProductPrice.
     *
     * @return int
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the price.
     *
     * @return float
     * @VirtualProperty
     * @SerializedName("price")
     */
    public function getPrice()
    {
        return $this->entity->getPrice();
    }

    /**
     * Sets the price.
     *
     * @param float $price
     */
    public function setPrice($price)
    {
        $this->entity->setPrice($price);
    }

    /**
     * Returns the currency.
     *
     * @return Currency
     * @VirtualProperty
     * @SerializedName("currency")
     */
    public function getCurrency()
    {
        return new Currency($this->entity->getCurrency(), $this->locale);
    }

    /**
     * Returns the minimumQuantity.
     *
     * @return float
     * @VirtualProperty
     * @SerializedName("minimumQuantity")
     */
    public function getMinimumQuantity()
    {
        return $this->entity->getMinimumQuantity();
    }
}
