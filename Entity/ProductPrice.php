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
    private $minimumQuantity = 1;

    /**
     * @var double
     */
    private $price;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    private $product;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    private $currency;

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
     * @param double $price
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
     * @return double
     */
    public function getPrice()
    {
        return $this->price;
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
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     * @return ProductPrice
     */
    public function setProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $product = null)
    {
        $this->product = $product;
    
        return $this;
    }

    /**
     * Get product
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set currency
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Currency $currency
     * @return ProductPrice
     */
    public function setCurrency(\Sulu\Bundle\ProductBundle\Entity\Currency $currency = null)
    {
        $this->currency = $currency;
    
        return $this;
    }

    /**
     * Get currency
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }
}
