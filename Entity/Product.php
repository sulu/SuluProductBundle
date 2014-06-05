<?php

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Product
 */
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
    private $prices;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $children;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $setProducts;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->productAttributes = new \Doctrine\Common\Collections\ArrayCollection();
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->addons = new \Doctrine\Common\Collections\ArrayCollection();
        $this->prices = new \Doctrine\Common\Collections\ArrayCollection();
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->setProducts = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add productAttributes
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceAttribute $productAttributes
     * @return Product
     */
    public function addProductAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;
    
        return $this;
    }

    /**
     * Remove productAttributes
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceAttribute $productAttributes
     */
    public function removeProductAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceAttribute $productAttributes)
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
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceTranslation $translations
     * @return Product
     */
    public function addTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfaceTranslation $translations)
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
     * Add addons
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Addon $addons
     * @return Product
     */
    public function addAddon(\Sulu\Bundle\Product\BaseBundle\Entity\Addon $addons)
    {
        $this->addons[] = $addons;
    
        return $this;
    }

    /**
     * Remove addons
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Addon $addons
     */
    public function removeAddon(\Sulu\Bundle\Product\BaseBundle\Entity\Addon $addons)
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
     * Add prices
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfacePrice $prices
     * @return Product
     */
    public function addPrice(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfacePrice $prices)
    {
        $this->prices[] = $prices;
    
        return $this;
    }

    /**
     * Remove prices
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfacePrice $prices
     */
    public function removePrice(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterfacePrice $prices)
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
     * Add children
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $children
     * @return Product
     */
    public function addChildren(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $children)
    {
        $this->children[] = $children;
    
        return $this;
    }

    /**
     * Remove children
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $children
     */
    public function removeChildren(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $children)
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
     * Add setProducts
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $setProducts
     * @return Product
     */
    public function addSetProduct(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $setProducts)
    {
        $this->setProducts[] = $setProducts;
    
        return $this;
    }

    /**
     * Remove setProducts
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $setProducts
     */
    public function removeSetProduct(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $setProducts)
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
}
