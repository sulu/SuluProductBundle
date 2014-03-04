<?php

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Product
 */
class Product
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $number;

    /**
     * @var boolean
     */
    private $active;

    /**
     * @var string
     */
    private $manufacturer;

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
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\ProductType
     */
    private $productType;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $sets;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $relations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $upsells;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $crosssells;

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
        $this->sets = new \Doctrine\Common\Collections\ArrayCollection();
        $this->relations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->upsells = new \Doctrine\Common\Collections\ArrayCollection();
        $this->crosssells = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Set key
     *
     * @param string $key
     * @return Product
     */
    public function setKey($key)
    {
        $this->key = $key;
    
        return $this;
    }

    /**
     * Get key
     *
     * @return string 
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set number
     *
     * @param string $number
     * @return Product
     */
    public function setNumber($number)
    {
        $this->number = $number;
    
        return $this;
    }

    /**
     * Get number
     *
     * @return string 
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set active
     *
     * @param boolean $active
     * @return Product
     */
    public function setActive($active)
    {
        $this->active = $active;
    
        return $this;
    }

    /**
     * Get active
     *
     * @return boolean 
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set manufacturer
     *
     * @param string $manufacturer
     * @return Product
     */
    public function setManufacturer($manufacturer)
    {
        $this->manufacturer = $manufacturer;
    
        return $this;
    }

    /**
     * Get manufacturer
     *
     * @return string 
     */
    public function getManufacturer()
    {
        return $this->manufacturer;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Product
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
     * @return Product
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
     * Set productType
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductType $productType
     * @return Product
     */
    public function setProductType(\Sulu\Bundle\Product\BaseBundle\Entity\ProductType $productType = null)
    {
        $this->productType = $productType;
    
        return $this;
    }

    /**
     * Get productType
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\ProductType 
     */
    public function getProductType()
    {
        return $this->productType;
    }

    /**
     * Add sets
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Set $sets
     * @return Product
     */
    public function addSet(\Sulu\Bundle\Product\BaseBundle\Entity\Set $sets)
    {
        $this->sets[] = $sets;
    
        return $this;
    }

    /**
     * Remove sets
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Set $sets
     */
    public function removeSet(\Sulu\Bundle\Product\BaseBundle\Entity\Set $sets)
    {
        $this->sets->removeElement($sets);
    }

    /**
     * Get sets
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSets()
    {
        return $this->sets;
    }

    /**
     * Add relations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $relations
     * @return Product
     */
    public function addRelation(\Sulu\Bundle\Product\BaseBundle\Entity\Product $relations)
    {
        $this->relations[] = $relations;
    
        return $this;
    }

    /**
     * Remove relations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $relations
     */
    public function removeRelation(\Sulu\Bundle\Product\BaseBundle\Entity\Product $relations)
    {
        $this->relations->removeElement($relations);
    }

    /**
     * Get relations
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Add upsells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells
     * @return Product
     */
    public function addUpsell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells)
    {
        $this->upsells[] = $upsells;
    
        return $this;
    }

    /**
     * Remove upsells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells
     */
    public function removeUpsell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells)
    {
        $this->upsells->removeElement($upsells);
    }

    /**
     * Get upsells
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUpsells()
    {
        return $this->upsells;
    }

    /**
     * Add crosssells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells
     * @return Product
     */
    public function addCrosssell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells)
    {
        $this->crosssells[] = $crosssells;
    
        return $this;
    }

    /**
     * Remove crosssells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells
     */
    public function removeCrosssell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells)
    {
        $this->crosssells->removeElement($crosssells);
    }

    /**
     * Get crosssells
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getCrosssells()
    {
        return $this->crosssells;
    }

    /**
     * Set changer
     *
     * @param \Sulu\Bundle\SecurityBundle\Entity\User $changer
     * @return Product
     */
    public function setChanger(\Sulu\Bundle\SecurityBundle\Entity\User $changer = null)
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
     * @param \Sulu\Bundle\SecurityBundle\Entity\User $creator
     * @return Product
     */
    public function setCreator(\Sulu\Bundle\SecurityBundle\Entity\User $creator = null)
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