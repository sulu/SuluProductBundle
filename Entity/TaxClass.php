<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TaxClass
 */
class TaxClass
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $translations;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation $translations
     * @return TaxClass
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation $translations)
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
    private $products;


    /**
     * Add products
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Product $products
     * @return TaxClass
     */
    public function addProduct(\Sulu\Bundle\ProductBundle\Entity\Product $products)
    {
        $this->products[] = $products;
    
        return $this;
    }

    /**
     * Remove products
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Product $products
     */
    public function removeProduct(\Sulu\Bundle\ProductBundle\Entity\Product $products)
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
}
