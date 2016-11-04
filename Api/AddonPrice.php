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
use Sulu\Bundle\ProductBundle\Entity\AddonPrice as AddonPriceEntity;
use Sulu\Bundle\ProductBundle\Util\PriceFormatter;
use Sulu\Component\Rest\ApiWrapper;

/**
 * @ExclusionPolicy("all")
 */
class AddonPrice extends ApiWrapper
{
    /**
     * @var PriceFormatter
     */
    private $priceFormatter;

    /**
     * @param AddonPriceEntity $entity
     * @param string $locale
     * @param PriceFormatter $priceFormatter
     */
    public function __construct(AddonPriceEntity $entity, $locale, PriceFormatter $priceFormatter)
    {
        $this->entity = $entity;
        $this->locale = $locale;
        $this->priceFormatter = $priceFormatter;
    }

    /**
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
     * @VirtualProperty
     * @SerializedName("currency")
     *
     * @return string
     */
    public function getCurrency()
    {
        return new Currency($this->entity->getCurrency(), $this->locale);
    }

    /**
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
     * @VirtualProperty
     * @SerializedName("priceFormatted")
     *
     * @return string
     */
    public function getPriceFormatted()
    {
        return $this->priceFormatter->format($this->entity->getPrice(), null, $this->locale);
    }
}
