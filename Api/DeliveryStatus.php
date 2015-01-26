<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus as Entity;
use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\ExclusionPolicy;
use Sulu\Component\Rest\ApiWrapper;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation;

/**
 * The DeliveryStatus class which will be exported to the API
 *
 * @ExclusionPolicy("all")
 */
class DeliveryStatus extends ApiWrapper
{
    /**
     * @param Entity $type
     * @param string $locale
     */
    public function __construct(Entity $type, $locale)
    {
        $this->entity = $type;
        $this->locale = $locale;
    }

    /**
     * The id of the type
     *
     * @return int The id of the type
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * The name of the type
     *
     * @return integer The name of the type
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the deliveryStatus
     * @param string $name The name of the delivery status
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * Returns the translation for the current locale
     *
     * @return DeliveryStatusTranslation
     */
    public function getTranslation()
    {
        $translation = null;
        foreach ($this->entity->getTranslations() as $translationData) {
            if ($translationData->getLocale() == $this->locale) {
                $translation = $translationData;
                break;
            }
        }

        return $translation;
    }
}
