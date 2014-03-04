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
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $translations;


    /**
     * Add translations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $translations
     * @return SimpleProduct
     */
    public function addTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $translations)
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
     * @var \Doctrine\Common\Collections\Collection
     */
    private $extras;


    /**
     * Add extras
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $extras
     * @return SimpleProduct
     */
    public function addExtra(\Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $extras)
    {
        $this->extras[] = $extras;
    
        return $this;
    }

    /**
     * Remove extras
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $extras
     */
    public function removeExtra(\Sulu\Bundle\Product\BaseBundle\Entity\ProductTranslation $extras)
    {
        $this->extras->removeElement($extras);
    }

    /**
     * Get extras
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getExtras()
    {
        return $this->extras;
    }
}