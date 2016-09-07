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
use Sulu\Bundle\ProductBundle\Entity\TaxClass as TaxClassEntity;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Component\Rest\ApiWrapper;

/**
 * @ExclusionPolicy("all")
 */
class TaxClass extends ApiWrapper
{
    /**
     * @var int
     */
    private $countryId;

    /**
     * @param TaxClassEntity $taxClass
     * @param string $locale
     * @param int|null $countryId
     */
    public function __construct(TaxClassEntity $taxClass, $locale, $countryId = null)
    {
        $this->entity = $taxClass;
        $this->locale = $locale;
        $this->countryId = $countryId;
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
     * @SerializedName("name")
     *
     * @return int
     */
    public function getName()
    {
        return $this->getTranslation($this->locale)->getName();
    }

    /**
     * @param string $locale
     *
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
     * @VirtualProperty
     * @SerializedName("countryTaxes")
     *
     * @return CountryTax[]|null
     */
    public function getCountryTaxes()
    {
        $taxes = null;
        if (!$this->entity->getCountryTaxes()->isEmpty()) {
            foreach ($this->entity->getCountryTaxes() as $countryTax) {
                // If countryId is defined, show only tax of this country
                if (!$this->countryId ||
                    ($this->countryId && $countryTax->getCountry()->getId() === $countryTax->getId())
                ) {
                    $taxes[] = new CountryTax($countryTax, $this->locale);
                    break;
                }
            }
        }

        return $taxes;
    }
}
