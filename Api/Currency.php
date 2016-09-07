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
use Sulu\Bundle\ProductBundle\Entity\Currency as Entity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The currency class which will be exported to the API.
 *
 * @ExclusionPolicy("all")
 */
class Currency extends ApiWrapper
{
    /**
     * @param \Sulu\Bundle\ProductBundle\Entity\Currency $currency
     * @param string $locale
     */
    public function __construct(Entity $currency, $locale)
    {
        $this->entity = $currency;
        $this->locale = $locale;
    }

    /**
     * The id of the currency.
     *
     * @return int The id of the currency
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * The name of the currency.
     *
     * @return int The name of the currency
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->entity->getName();
    }

    /**
     * The number of the currency.
     *
     * @return int The number of the currency
     * @VirtualProperty
     * @SerializedName("number")
     */
    public function getNumber()
    {
        return $this->entity->getNumber();
    }

    /**
     * The iso code of the currency.
     *
     * @return int The iso code of the currency
     * @VirtualProperty
     * @SerializedName("code")
     */
    public function getCode()
    {
        return $this->entity->getCode();
    }
}
