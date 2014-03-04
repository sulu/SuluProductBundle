<?php

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductType
 */
class ProductType
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $products;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $productTypeTranslations;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();
        $this->productTypeTranslations = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add products
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $products
     * @return ProductType
     */
    public function addProduct(\Sulu\Bundle\Product\BaseBundle\Entity\Product $products)
    {
        $this->products[] = $products;
    
        return $this;
    }

    /**
     * Remove products
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $products
     */
    public function removeProduct(\Sulu\Bundle\Product\BaseBundle\Entity\Product $products)
    {
        $this->products->removeElement($products);
    }

    /**
     * Get products
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * Add productTypeTranslations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductTypeTranslation $productTypeTranslations
     * @return ProductType
     */
    public function addProductTypeTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\ProductTypeTranslation $productTypeTranslations)
    {
        $this->productTypeTranslations[] = $productTypeTranslations;
    
        return $this;
    }

    /**
     * Remove productTypeTranslations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductTypeTranslation $productTypeTranslations
     */
    public function removeProductTypeTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\ProductTypeTranslation $productTypeTranslations)
    {
        $this->productTypeTranslations->removeElement($productTypeTranslations);
    }

    /**
     * Get productTypeTranslations
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getProductTypeTranslations()
    {
        return $this->productTypeTranslations;
    }
}
