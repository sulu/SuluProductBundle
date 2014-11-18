<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Unit
 */
class Unit
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
     * Constructor
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set id
     *
     * @param $id
     * @return Unit
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * @param \Sulu\Bundle\ProductBundle\Entity\UnitTranslation $translations
     * @return Unit
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\UnitTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\UnitTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\UnitTranslation $translations)
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
     * @param \Sulu\Bundle\ProductBundle\Entity\Product $products
     * @return Unit
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
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $mappings;


    /**
     * Add mappings
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\UnitMapping $mappings
     * @return Unit
     */
    public function addMapping(\Sulu\Bundle\ProductBundle\Entity\UnitMapping $mappings)
    {
        $this->mappings[] = $mappings;

        return $this;
    }

    /**
     * Remove mappings
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\UnitMapping $mappings
     */
    public function removeMapping(\Sulu\Bundle\ProductBundle\Entity\UnitMapping $mappings)
    {
        $this->mappings->removeElement($mappings);
    }

    /**
     * Get mappings
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getMappings()
    {
        return $this->mappings;
    }

    /**
     * Returns the translation for the given locale
     *
     * @param string $locale
     * @return TypeTranslation
     */
    public function getTranslation($locale)
    {
        $translation = null;
        /** @var UnitTranslation $translationData */
        foreach ($this->translations as $translationData) {
            if ($translationData->getLocale() == $locale) {
                $translation = $translationData;
                break;
            }
        }

        return $translation;
    }
}
