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

use Doctrine\Common\Collections\Collection;

class TaxClass
{
    const STANDARD_TAX_RATE = 1;
    const REDUCED_TAX_RATE = 2;

    /**
     * @var int
     */
    private $id;

    /**
     * @var Collection
     */
    private $translations;

    /**
     * @var Collection
     */
    private $products;

    /**
     * @var Collection
     */
    private $countryTaxes;

    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param TaxClassTranslation $translations
     *
     * @return self
     */
    public function addTranslation(TaxClassTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * @param TaxClassTranslation $translations
     */
    public function removeTranslation(TaxClassTranslation $translations)
    {
        $this->translations->removeElement($translations);
    }

    /**
     * @return Collection
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * @param string $locale
     *
     * @return null|TaxClassTranslation
     */
    public function getTranslation($locale)
    {
        /** @var TaxClassTranslation $translation */
        foreach ($this->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @param Product $product
     *
     * @return self
     */
    public function addProduct(Product $product)
    {
        $this->products[] = $product;

        return $this;
    }

    /**
     * @param Product $product
     */
    public function removeProduct(Product $product)
    {
        $this->products->removeElement($product);
    }

    /**
     * @return Collection
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * @param CountryTax $tax
     *
     * @return self
     */
    public function addCountryTax(CountryTax $tax)
    {
        $this->countryTaxes[] = $tax;

        return $this;
    }

    /**
     * @param CountryTax $tax
     */
    public function removeCountryTax(CountryTax $tax)
    {
        $this->countryTaxes->removeElement($tax);
    }

    /**
     * @return Collection
     */
    public function getCountryTaxes()
    {
        return $this->countryTaxes;
    }
}
