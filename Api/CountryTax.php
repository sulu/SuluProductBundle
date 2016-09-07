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
use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\ProductBundle\Entity\CountryTax as CountryTaxEntity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * @ExclusionPolicy("all")
 */
class CountryTax extends ApiWrapper
{
    /**
     * @param CountryTaxEntity $entity
     * @param string $locale
     */
    public function __construct(CountryTaxEntity $entity, $locale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
    }

    /**
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * @VirtualProperty
     * @SerializedName("country")
     *
     * @return Country
     */
    public function getCountry()
    {
        return $this->entity->getCountry();
    }

    /**
     * @return TaxClass
     */
    public function getTaxClass()
    {
        return new TaxClass($this->entity->getTaxClass(), $this->locale);
    }

    /**
     * @VirtualProperty
     * @SerializedName("tax")
     *
     * @return float
     */
    public function getTax()
    {
        return $this->entity->getTax();
    }
}
