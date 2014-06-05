<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * DeliveryStatusTranslation
 */
class DeliveryStatusTranslation
{
    /**
     * @var string
     */
    private $languageCode;

    /**
     * @var string
     */
    private $name;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    private $deliveryStatus;


    /**
     * Set languageCode
     *
     * @param string $languageCode
     * @return DeliveryStatusTranslation
     */
    public function setLanguageCode($languageCode)
    {
        $this->languageCode = $languageCode;
    
        return $this;
    }

    /**
     * Get languageCode
     *
     * @return string 
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return DeliveryStatusTranslation
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set deliveryStatus
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus
     * @return DeliveryStatusTranslation
     */
    public function setDeliveryStatus(\Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus)
    {
        $this->deliveryStatus = $deliveryStatus;
    
        return $this;
    }

    /**
     * Get deliveryStatus
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    public function getDeliveryStatus()
    {
        return $this->deliveryStatus;
    }
}
