<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BaseProduct
 */
abstract class BaseProduct implements ProductInterface
{
    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $number;

    /**
     * @var string
     */
    private $manufacturer;

    /**
     * @var string
     */
    private $cost;

    /**
     * @var string
     */
    private $price;

    /**
     * @var string
     */
    private $priceInfo;

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
     * @var \Sulu\Bundle\ContactBundle\Entity\Country
     */
    private $manufacturerCountry;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Type
     */
    private $type;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    private $attributeSet;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Status
     */
    private $status;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    private $deliveryStatus;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    private $parent;

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
     * Set code
     *
     * @param string $code
     * @return BaseProduct
     */
    public function setCode($code)
    {
        $this->code = $code;
    
        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set number
     *
     * @param string $number
     * @return BaseProduct
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
     * Set manufacturer
     *
     * @param string $manufacturer
     * @return BaseProduct
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
     * Set cost
     *
     * @param string $cost
     * @return BaseProduct
     */
    public function setCost($cost)
    {
        $this->cost = $cost;
    
        return $this;
    }

    /**
     * Get cost
     *
     * @return string 
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     * Set price
     *
     * @param string $price
     * @return BaseProduct
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
     * @return BaseProduct
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
     * Set created
     *
     * @param \DateTime $created
     * @return BaseProduct
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
     * @return BaseProduct
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
     * Set manufacturerCountry
     *
     * @param \Sulu\Bundle\ContactBundle\Entity\Country $manufacturerCountry
     * @return BaseProduct
     */
    public function setManufacturerCountry(\Sulu\Bundle\ContactBundle\Entity\Country $manufacturerCountry = null)
    {
        $this->manufacturerCountry = $manufacturerCountry;
    
        return $this;
    }

    /**
     * Get manufacturerCountry
     *
     * @return \Sulu\Bundle\ContactBundle\Entity\Country 
     */
    public function getManufacturerCountry()
    {
        return $this->manufacturerCountry;
    }

    /**
     * Set type
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Type $type
     * @return BaseProduct
     */
    public function setType(\Sulu\Bundle\ProductBundle\Entity\Type $type)
    {
        $this->type = $type;
    
        return $this;
    }

    /**
     * Get type
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Type 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set attributeSet
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeSet $attributeSet
     * @return BaseProduct
     */
    public function setAttributeSet(\Sulu\Bundle\ProductBundle\Entity\AttributeSet $attributeSet)
    {
        $this->attributeSet = $attributeSet;
    
        return $this;
    }

    /**
     * Get attributeSet
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeSet 
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }

    /**
     * Set status
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Status $status
     * @return BaseProduct
     */
    public function setStatus(\Sulu\Bundle\ProductBundle\Entity\Status $status = null)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Status 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set deliveryStatus
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus
     * @return BaseProduct
     */
    public function setDeliveryStatus(\Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus = null)
    {
        $this->deliveryStatus = $deliveryStatus;
    
        return $this;
    }

    /**
     * Get deliveryStatus
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus 
     */
    public function getDeliveryStatus()
    {
        return $this->deliveryStatus;
    }

    /**
     * Set parent
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $parent
     * @return BaseProduct
     */
    public function setParent(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $parent = null)
    {
        $this->parent = $parent;
    
        return $this;
    }

    /**
     * Get parent
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface 
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add sets
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets
     * @return BaseProduct
     */
    public function addSet(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets)
    {
        $this->sets[] = $sets;
    
        return $this;
    }

    /**
     * Remove sets
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets
     */
    public function removeSet(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets)
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
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations
     * @return BaseProduct
     */
    public function addRelation(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations)
    {
        $this->relations[] = $relations;
    
        return $this;
    }

    /**
     * Remove relations
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations
     */
    public function removeRelation(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations)
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
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells
     * @return BaseProduct
     */
    public function addUpsell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells)
    {
        $this->upsells[] = $upsells;
    
        return $this;
    }

    /**
     * Remove upsells
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells
     */
    public function removeUpsell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells)
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
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells
     * @return BaseProduct
     */
    public function addCrosssell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells)
    {
        $this->crosssells[] = $crosssells;
    
        return $this;
    }

    /**
     * Remove crosssells
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells
     */
    public function removeCrosssell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells)
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
     * @return BaseProduct
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
     * @return BaseProduct
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
