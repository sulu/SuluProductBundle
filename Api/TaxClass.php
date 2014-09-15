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

use Sulu\Bundle\ProductBundle\Entity\TaxClass as Entity;
use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Component\Rest\ApiWrapper;

class TaxClass extends ApiWrapper
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
     * The id of the taxClass
     * @return int The id of the taxClass
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * The name of the taxClass
     * @return int The name of the taxClass
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->getTranslation($this->locale)->getName();
    }

    /**
     * Returns the translation for the given locale
     * @param string $locale
     * @return TaxClassTranslation
     */
    public function getTranslation($locale)
    {
        $translation = null;
        foreach ($this->entity->getTranslations() as $translationData) {
            /** @var TaxClassTranslation $translationData */
            if ($translationData->getLocale() == $locale) {
                $translation = $translationData;
                break;
            }
        }

        return $translation;
    }
} 
