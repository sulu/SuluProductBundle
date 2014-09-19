<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Component\Security\UserInterface;

/**
 * Attribute
 */
class Attribute
{

    const ATTRIBUTE_TYPE_TEXT = 1;
    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $changed;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    public $translations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $values;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $productAttributes;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeType
     */
    private $type;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    private $changer;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    private $creator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->values = new \Doctrine\Common\Collections\ArrayCollection();
        $this->productAttributes = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Attribute
     */
    public function setCreated($created)
    {
        $this->created = $created;
    
        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set changed
     *
     * @param \DateTime $changed
     * @return Attribute
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;
    
        return $this;
    }

    /**
     * Get changed
     *
     * @return \DateTime 
     */
    public function getChanged()
    {
        return $this->changed;
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
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeTranslation $translations
     * @return Attribute
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\AttributeTranslation $translations)
    {
        $this->translations[] = $translations;
    
        return $this;
    }

    /**
     * Remove translations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\AttributeTranslation $translations)
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
     * Add values
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValue $values
     * @return Attribute
     */
    public function addValue(\Sulu\Bundle\ProductBundle\Entity\AttributeValue $values)
    {
        $this->values[] = $values;
    
        return $this;
    }

    /**
     * Remove values
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValue $values
     */
    public function removeValue(\Sulu\Bundle\ProductBundle\Entity\AttributeValue $values)
    {
        $this->values->removeElement($values);
    }

    /**
     * Get values
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Add productAttributes
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes
     * @return Attribute
     */
    public function addProductAttribute(\Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;
    
        return $this;
    }

    /**
     * Remove productAttributes
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes
     */
    public function removeProductAttribute(\Sulu\Bundle\ProductBundle\Entity\ProductAttribute $productAttributes)
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
     * Set type
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeType $type
     * @return Attribute
     */
    public function setType(\Sulu\Bundle\ProductBundle\Entity\AttributeType $type)
    {
        $this->type = $type;
    
        return $this;
    }

    /**
     * Get type
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeType 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set changer
     *
     * @param \Sulu\Component\Security\UserInterface $changer
     * @return Attribute
     */
    public function setChanger(UserInterface $changer = null)
    {
        $this->changer = $changer;
    
        return $this;
    }

    /**
     * Get changer
     *
     * @return \Sulu\Bundle\SecurityBundle\Entity\User 
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator
     *
     * @param \Sulu\Component\Security\UserInterface $creator
     * @return Attribute
     */
    public function setCreator(UserInterface $creator = null)
    {
        $this->creator = $creator;
    
        return $this;
    }

    /**
     * Get creator
     *
     * @return \Sulu\Bundle\SecurityBundle\Entity\User 
     */
    public function getCreator()
    {
        return $this->creator;
    }
}
