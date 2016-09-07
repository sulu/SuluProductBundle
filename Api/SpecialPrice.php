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
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice as SpecialPriceEntity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The Special price class which will be exported to the API.
 *
 * @ExclusionPolicy("all")
 */
class SpecialPrice extends ApiWrapper
{
    /**
     * @param SpecialPriceEntity $entity
     * @param string $locale
     */
    public function __construct(SpecialPriceEntity $entity, $locale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
    }

    /**
     * Returns the id of the Special price.
     *
     * @VirtualProperty
     * @SerializedName("id")
     *
     * @return int
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Get price.
     *
     * @VirtualProperty
     * @SerializedName("price")
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->entity->getPrice();
    }

    /**
     * Get start date.
     *
     * @VirtualProperty
     * @SerializedName("startDate")
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->entity->getStartDate();
    }

    /**
     * Get end date.
     *
     * @VirtualProperty
     * @SerializedName("endDate")
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->entity->getEndDate();
    }

    /**
     * Get currency.
     *
     * @VirtualProperty
     * @SerializedName("currency")
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    public function getCurrency()
    {
        return $this->entity->getCurrency();
    }

    /**
     * Get product.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->entity->getProduct();
    }
}
