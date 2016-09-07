<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Entity;

/**
 * DeliveryStatusTranslation.
 */
class DeliveryStatusTranslation
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    private $deliveryStatus;

    /**
     * Set languageCode.
     *
     * @param string $languageCode
     *
     * @return DeliveryStatusTranslation
     */
    public function setLocale($languageCode)
    {
        $this->locale = $languageCode;

        return $this;
    }

    /**
     * Get languageCode.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return DeliveryStatusTranslation
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set deliveryStatus.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus
     *
     * @return DeliveryStatusTranslation
     */
    public function setDeliveryStatus(\Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus)
    {
        $this->deliveryStatus = $deliveryStatus;

        return $this;
    }

    /**
     * Get deliveryStatus.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    public function getDeliveryStatus()
    {
        return $this->deliveryStatus;
    }
}
