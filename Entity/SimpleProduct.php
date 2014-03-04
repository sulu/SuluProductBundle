<?php

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SimpleProduct
 */
class SimpleProduct extends Product
{
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $productAttributes;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->productAttributes = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add productAttributes
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductAttribute $productAttributes
     * @return SimpleProduct
     */
    public function addProductAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\ProductAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;
    
        return $this;
    }

    /**
     * Remove productAttributes
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductAttribute $productAttributes
     */
    public function removeProductAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\ProductAttribute $productAttributes)
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
}
