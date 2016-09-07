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

use Doctrine\Common\Collections\ArrayCollection;

class Product extends BaseProduct
{
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $productAttributes;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $translations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $addons;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $children;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $prices;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $setProducts;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $specialPrices;

    /**
     * @var bool
     */
    protected $isRecurringPrice = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->productAttributes = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->addons = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->prices = new ArrayCollection();
        $this->setProducts = new ArrayCollection();
        $this->specialPrices = new ArrayCollection();
    }

    /**
     * Add productAttributes.
     *
     * @param ProductAttribute $productAttributes
     *
     * @return Product
     */
    public function addProductAttribute(ProductAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;

        return $this;
    }

    /**
     * Remove productAttributes.
     *
     * @param ProductAttribute $productAttributes
     */
    public function removeProductAttribute(ProductAttribute $productAttributes)
    {
        $this->productAttributes->removeElement($productAttributes);
    }

    /**
     * Get productAttributes.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProductAttributes()
    {
        return $this->productAttributes;
    }

    /**
     * Add translations.
     *
     * @param ProductTranslation $translations
     *
     * @return Product
     */
    public function addTranslation(ProductTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * Remove translations.
     *
     * @param ProductTranslation $translations
     */
    public function removeTranslation(ProductTranslation $translations)
    {
        $this->translations->removeElement($translations);
    }

    /**
     * Get translations.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslation($locale)
    {
        $translation = null;
        foreach ($this->translations as $translationData) {
            if ($translationData->getLocale() == $locale) {
                $translation = $translationData;
                break;
            }
        }

        return $translation;
    }

    /**
     * Add addons.
     *
     * @param Addon $addons
     *
     * @return Product
     */
    public function addAddon(Addon $addons)
    {
        $this->addons[] = $addons;

        return $this;
    }

    /**
     * Remove addons.
     *
     * @param Addon $addons
     */
    public function removeAddon(Addon $addons)
    {
        $this->addons->removeElement($addons);
    }

    /**
     * Get addons.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAddons()
    {
        return $this->addons;
    }

    /**
     * Add children.
     *
     * @param ProductInterface $children
     *
     * @return Product
     */
    public function addChildren(ProductInterface $children)
    {
        $this->children[] = $children;

        return $this;
    }

    /**
     * Remove children.
     *
     * @param ProductInterface $children
     */
    public function removeChildren(ProductInterface $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Get children.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Add prices.
     *
     * @param ProductPrice $prices
     *
     * @return Product
     */
    public function addPrice(ProductPrice $prices)
    {
        $this->prices[] = $prices;

        return $this;
    }

    /**
     * Remove prices.
     *
     * @param ProductPrice $prices
     */
    public function removePrice(ProductPrice $prices)
    {
        $this->prices->removeElement($prices);
    }

    /**
     * Get prices.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPrices()
    {
        return $this->prices;
    }

    /**
     * Add setProducts.
     *
     * @param ProductInterface $setProducts
     *
     * @return Product
     */
    public function addSetProduct(ProductInterface $setProducts)
    {
        $this->setProducts[] = $setProducts;

        return $this;
    }

    /**
     * Remove setProducts.
     *
     * @param ProductInterface $setProducts
     */
    public function removeSetProduct(ProductInterface $setProducts)
    {
        $this->setProducts->removeElement($setProducts);
    }

    /**
     * Get setProducts.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSetProducts()
    {
        return $this->setProducts;
    }

    /**
     * Get special prices.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSpecialPrices()
    {
        return $this->specialPrices;
    }

    /**
     * Add special price.
     *
     * @param SpecialPrice $specialPrice
     *
     * @return Product
     */
    public function addSpecialPrice(SpecialPrice $specialPrice)
    {
        $this->specialPrices[] = $specialPrice;

        return $this;
    }

    /**
     * Remove special prices.
     *
     * @param SpecialPrice $specialPrices
     */
    public function removeSpecialPrice(SpecialPrice $specialPrices)
    {
        $this->specialPrices->removeElement($specialPrices);
    }

    /**
     * @return bool
     */
    public function isRecurringPrice()
    {
        return $this->isRecurringPrice;
    }

    /**
     * @param bool $isRecurringPrice
     *
     * @return self
     */
    public function setIsRecurringPrice($isRecurringPrice)
    {
        $this->isRecurringPrice = $isRecurringPrice;

        return $this;
    }
}
