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
use Doctrine\Common\Collections\Collection;

class Product extends BaseProduct
{
    /**
     * @var Collection
     */
    protected $variantAttributes;

    /**
     * @var Collection
     */
    protected $productAttributes;

    /**
     * @var Collection
     */
    protected $translations;

    /**
     * @var Collection
     */
    protected $addons;

    /**
     * @var Collection
     */
    protected $children;

    /**
     * @var Collection
     */
    protected $prices;

    /**
     * @var Collection
     */
    protected $setProducts;

    /**
     * @var Collection
     */
    protected $specialPrices;

    /**
     * @var bool
     */
    protected $isRecurringPrice = false;

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
     * @param ProductAttribute $productAttributes
     *
     * @return self
     */
    public function addProductAttribute(ProductAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;

        return $this;
    }

    /**
     * @param ProductAttribute $productAttributes
     *
     * @return self
     */
    public function removeProductAttribute(ProductAttribute $productAttributes)
    {
        $this->productAttributes->removeElement($productAttributes);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getProductAttributes()
    {
        return $this->productAttributes;
    }

    /**
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
     * @param ProductTranslation $translations
     *
     * @return self
     */
    public function removeTranslation(ProductTranslation $translations)
    {
        $this->translations->removeElement($translations);

        return $this;
    }

    /**
     * @return Collection
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
     * @param Addon $addons
     *
     * @return self
     */
    public function addAddon(Addon $addons)
    {
        $this->addons[] = $addons;

        return $this;
    }

    /**
     * @param Addon $addons
     *
     * @return self
     */
    public function removeAddon(Addon $addons)
    {
        $this->addons->removeElement($addons);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getAddons()
    {
        return $this->addons;
    }

    /**
     * @param ProductInterface $children
     *
     * @return self
     */
    public function addChildren(ProductInterface $children)
    {
        $this->children[] = $children;

        return $this;
    }

    /**
     * @param ProductInterface $children
     *
     * @return self
     */
    public function removeChildren(ProductInterface $children)
    {
        $this->children->removeElement($children);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param ProductPrice $prices
     *
     * @return self
     */
    public function addPrice(ProductPrice $prices)
    {
        $this->prices[] = $prices;

        return $this;
    }

    /**
     * @param ProductPrice $prices
     *
     * @return self
     */
    public function removePrice(ProductPrice $prices)
    {
        $this->prices->removeElement($prices);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getPrices()
    {
        return $this->prices;
    }

    /**
     * @param ProductInterface $setProducts
     *
     * @return self
     */
    public function addSetProduct(ProductInterface $setProducts)
    {
        $this->setProducts[] = $setProducts;

        return $this;
    }

    /**
     * @param ProductInterface $setProducts
     *
     * @return self
     */
    public function removeSetProduct(ProductInterface $setProducts)
    {
        $this->setProducts->removeElement($setProducts);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getSetProducts()
    {
        return $this->setProducts;
    }

    /**
     * @return Collection
     */
    public function getSpecialPrices()
    {
        return $this->specialPrices;
    }

    /**
     * @param SpecialPrice $specialPrice
     *
     * @return self
     */
    public function addSpecialPrice(SpecialPrice $specialPrice)
    {
        $this->specialPrices[] = $specialPrice;

        return $this;
    }

    /**
     * @param SpecialPrice $specialPrices
     *
     * @return self
     */
    public function removeSpecialPrice(SpecialPrice $specialPrices)
    {
        $this->specialPrices->removeElement($specialPrices);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getVariantAttributes()
    {
        return $this->variantAttributes;
    }

    /**
     * @param Attribute $attribute
     *
     * @return self
     */
    public function addVariantAttribute(Attribute $attribute)
    {
        $this->variantAttributes[] = $attribute;

        return $this;
    }

    /**
     * @param Attribute[] $attributes
     *
     * @return self
     */
    public function addVariantAttributes(array $attributes)
    {
        foreach ($attributes as $attribute) {
            $this->addVariantAttribute($attribute);
        }

        return $this;
    }

    /**
     * @param Attribute $attribute
     *
     * @return self
     */
    public function removeVariantAttribute(Attribute $attribute)
    {
        $this->variantAttributes->removeElement($attribute);

        return $this;
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
