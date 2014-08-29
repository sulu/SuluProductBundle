<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AttributeValue
 */
class AttributeValue
{
    /**
     * @var boolean
     */
    private $selected;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $translations;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Attribute
     */
    private $attribute;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Set selected
     *
     * @param boolean $selected
     * @return AttributeValue
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
    
        return $this;
    }

    /**
     * Get selected
     *
     * @return boolean 
     */
    public function getSelected()
    {
        return $this->selected;
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
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation $translations
     * @return AttributeValue
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation $translations)
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
     * Returns the translation with the given locale
     * @param string $locale The locale to return
     * @return AttributeValueTranslation
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
     * Set attribute
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Attribute $attribute
     * @return AttributeValue
     */
    public function setAttribute(\Sulu\Bundle\ProductBundle\Entity\Attribute $attribute)
    {
        $this->attribute = $attribute;
    
        return $this;
    }

    /**
     * Get attribute
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Attribute 
     */
    public function getAttribute()
    {
        return $this->attribute;
    }
}
