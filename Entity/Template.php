<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Template
 */
class Template
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
     * @var \Doctrine\Common\Collections\Collection
     */
    private $products;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $attributes;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();
        $this->attributes = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\TemplateTranslation $translations
     * @return Template
     */
    public function addTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\TemplateTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\TemplateTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\Product\BaseBundle\Entity\TemplateTranslation $translations)
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
     * Add products
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $products
     * @return Template
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
     * Add attributes
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Attribute $attributes
     * @return Template
     */
    public function addAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\Attribute $attributes)
    {
        $this->attributes[] = $attributes;
    
        return $this;
    }

    /**
     * Remove attributes
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Attribute $attributes
     */
    public function removeAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\Attribute $attributes)
    {
        $this->attributes->removeElement($attributes);
    }

    /**
     * Get attributes
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
}
