<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

class Product extends BaseProduct
{
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $productAttributes;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $translations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $addons;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $children;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $prices;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $setProducts;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $specialPrices;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->productAttributes = new \Doctrine\Common\Collections\ArrayCollection();
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->addons = new \Doctrine\Common\Collections\ArrayCollection();
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->prices = new \Doctrine\Common\Collections\ArrayCollection();
        $this->setProducts = new \Doctrine\Common\Collections\ArrayCollection();
        $this->specialPrices = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add productAttributes
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes
     * @return Product
     */
    public function addProductAttribute(\Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;

        return $this;
    }

    /**
     * Remove productAttributes
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes
     */
    public function removeProductAttribute(\Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes)
    {
        $this->productAttributes->removeElement($productAttributes);
    }

    /**
     * Get productAttributes
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getProductAttributes()
    {
        return $this->productAttributes;
    }

    /**
     * Add translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductTranslation $translations
     * @return Product
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\ProductTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\ProductTranslation $translations)
    {
        $this->translations->removeElement($translations);
    }

    /**
     * Get translations
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * {@inheritDoc}
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
     * Add addons
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Addon $addons
     * @return Product
     */
    public function addAddon(\Sulu\Bundle\ProductBundle\Entity\Addon $addons)
    {
        $this->addons[] = $addons;
    
        return $this;
    }

    /**
     * Remove addons
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Addon $addons
     */
    public function removeAddon(\Sulu\Bundle\ProductBundle\Entity\Addon $addons)
    {
        $this->addons->removeElement($addons);
    }

    /**
     * Get addons
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getAddons()
    {
        return $this->addons;
    }

    /**
     * Add children
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $children
     * @return Product
     */
    public function addChildren(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $children)
    {
        $this->children[] = $children;
    
        return $this;
    }

    /**
     * Remove children
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $children
     */
    public function removeChildren(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Add prices
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices
     * @return Product
     */
    public function addPrice(\Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices)
    {
        $this->prices[] = $prices;
    
        return $this;
    }

    /**
     * Remove prices
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices
     */
    public function removePrice(\Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices)
    {
        $this->prices->removeElement($prices);
    }

    /**
     * Get prices
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getPrices()
    {
        return $this->prices;
    }

    /**
     * Add setProducts
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts
     * @return Product
     */
    public function addSetProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts)
    {
        $this->setProducts[] = $setProducts;
    
        return $this;
    }

    /**
     * Remove setProducts
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts
     */
    public function removeSetProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts)
    {
        $this->setProducts->removeElement($setProducts);
    }

    /**
     * Get setProducts
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSetProducts()
    {
        return $this->setProducts;
    }

    /**
     * Get special prices
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSpecialPrices()
    {
        return $this->specialPrices;
    }

    /**
     * Add special price
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\SpecialPrice $specialPrice
     *
     * @return Product
     */
    public function addSpecialPrice(\Sulu\Bundle\ProductBundle\Entity\SpecialPrice $specialPrice)
    {
        $this->specialPrices[] = $specialPrice;

        return $this;
    }

    /**
     * Remove special prices
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\SpecialPrice $specialPrices
     */
    public function removeSpecialPrice(\Sulu\Bundle\ProductBundle\Entity\SpecialPrice $specialPrices)
    {
        $this->specialPrices->removeElement($specialPrices);
    }
}
