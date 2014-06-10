<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductPrice
 */
class ProductPrice
{
    /**
     * @var string
     */
    private $minimumQuantity;

    /**
     * @var string
     */
    private $price;

    /**
     * @var string
     */
    private $priceInfo;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Product
     */
    private $product;


    /**
     * Set minimumQuantity
     *
     * @param string $minimumQuantity
     * @return ProductPrice
     */
    public function setMinimumQuantity($minimumQuantity)
    {
        $this->minimumQuantity = $minimumQuantity;
    
        return $this;
    }

    /**
     * Get minimumQuantity
     *
     * @return string 
     */
    public function getMinimumQuantity()
    {
        return $this->minimumQuantity;
    }

    /**
     * Set price
     *
     * @param string $price
     * @return ProductPrice
     */
    public function setPrice($price)
    {
        $this->price = $price;
    
        return $this;
    }

    /**
     * Get price
     *
     * @return string 
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set priceInfo
     *
     * @param string $priceInfo
     * @return ProductPrice
     */
    public function setPriceInfo($priceInfo)
    {
        $this->priceInfo = $priceInfo;
    
        return $this;
    }

    /**
     * Get priceInfo
     *
     * @return string 
     */
    public function getPriceInfo()
    {
        return $this->priceInfo;
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
     * Set product
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Product $product
     * @return ProductPrice
     */
    public function setProduct(\Sulu\Bundle\ProductBundle\Entity\Product $product = null)
    {
        $this->product = $product;
    
        return $this;
    }

    /**
     * Get product
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Product
     */
    public function getProduct()
    {
        return $this->product;
    }
}