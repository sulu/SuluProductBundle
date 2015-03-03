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
use JMS\Serializer\Annotation\ExclusionPolicy;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The TaxClass class which will be exported to the API
 *
 * @package Sulu\Bundle\ProductBundle\Api
 * @ExclusionPolicy("all")
 */
class TaxClass extends ApiWrapper
{
    private $countryId;

    /**
     * @param Entity $taxClass
     * @param string $locale
     * @param int|null $countryId
     * @internal param Entity $type
     */
    public function __construct(Entity $taxClass, $locale, $countryId = null)
    {
        $this->entity = $taxClass;
        $this->locale = $locale;
        $this->countryId = $countryId;
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

    /**
     * Returns the translation for the given locale
     * @return TaxClassTranslation
     * @VirtualProperty
     * @SerializedName("countryTaxes")
     */
    public function getCountryTaxes()
    {
        $taxes = null;
        if (!$this->entity->getCountryTaxes()->isEmpty()) {
            foreach ($this->entity->getCountryTaxes() as $countryTax) {
                // if countryId is defined, show only tax of this country
                if (!$this->countryId ||
                    ($this->countryId && $countryTax->getCountry()->getId() == $countryTax)
                ) {
                    $taxes[] = new CountryTax($countryTax, $this->locale);
                    break;
                }
            }
        }

        return $taxes;
    }
} 
