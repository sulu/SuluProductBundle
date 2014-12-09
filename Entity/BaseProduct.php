<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Component\Security\UserInterface;

/**
 * BaseProduct
 */
abstract class BaseProduct implements ProductInterface
{
    /**
     * @var string
     */
    private $number;

    /**
     * @var string
     */
    private $globalTradeItemNumber;

    /**
     * @var string
     */
    private $internalItemNumber;

    /**
     * @var isDeprecated
     */
    private $isDeprecated = false;

    /**
     * @var isArchived
     */
    private $isArchived = false;

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
     * @var \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    private $taxClass;

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
     * @var \Sulu\Bundle\ContactBundle\Entity\Account
     */
    private $supplier;

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
     * @var \Doctrine\Common\Collections\Collection
     */
    private $categories;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $media;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    private $changer;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    private $creator;

    /**
     * @var float
     */
    private $minimumOrderQuantity;

    /**
     * @var float
     */
    private $recommendedOrderQuantity;

    /**
     * @var float
     */
    private $orderContentRatio;

    /**
     * @var Unit
     */
    private $contentUnit;

    /**
     * @var Unit
     */
    private $orderUnit;


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
     * Set globalTradeItemNumber
     *
     * @param string $globalTradeItemNumber
     * @return BaseProduct
     */
    public function setGlobalTradeItemNumber($globalTradeItemNumber)
    {
        $this->globalTradeItemNumber = $globalTradeItemNumber;

        return $this;
    }

    /**
     * Get globalTradeItemNumber
     *
     * @return string
     */
    public function getGlobalTradeItemNumber()
    {
        return $this->globalTradeItemNumber;
    }

    /**
     * Set internalItemNumber
     *
     * @param string $internalItemNumber
     * @return BaseProduct
     */
    public function setInternalItemNumber($internalItemNumber)
    {
        $this->internalItemNumber = $internalItemNumber;

        return $this;
    }

    /**
     * Get internalItemNumber
     *
     * @return string
     */
    public function getInternalItemNumber()
    {
        return $this->internalItemNumber;
    }

    /**
     * Set isDeprecated
     *
     * @param boolean $isDeprecated
     * @return BaseProduct
     */
    public function setIsDeprecated($isDeprecated)
    {
        $this->isDeprecated = $isDeprecated;

        return $this;
    }

    /**
     * Get isDeprecated
     *
     * @return boolean
     */
    public function isDeprecated()
    {
        return $this->isDeprecated;
    }

    /**
     * Set isArchived
     *
     * @param boolean $isArchived
     * @return BaseProduct
     */
    public function setIsArchived($isArchived)
    {
        $this->isArchived = $isArchived;

        return $this;
    }

    /**
     * Get isArchived
     *
     * @return boolean
     */
    public function isArchived()
    {
        return $this->isArchived;
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
     * @param double $cost
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
     * @return double
     */
    public function getCost()
    {
        return $this->cost;
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
     * Set supplier
     *
     * @param \Sulu\Bundle\ContactBundle\Entity\Account $supplier
     * @return BaseProduct
     */
    public function setSupplier(\Sulu\Bundle\ContactBundle\Entity\Account $supplier = null)
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * Get supplier
     *
     * @return \Sulu\Bundle\ContactBundle\Entity\Account
     */
    public function getSupplier()
    {
        return $this->supplier;
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
     * @param \Sulu\Component\Security\UserInterface $changer
     * @return BaseProduct
     */
    public function setChanger(UserInterface $changer = null)
    {
        $this->changer = $changer;

        return $this;
    }

    /**
     * Get changer
     *
     * @return \Sulu\Component\Security\UserInterface
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator
     *
     * @param \Sulu\Component\Security\UserInterface $creator
     * @return BaseProduct
     */
    public function setCreator(UserInterface $creator = null)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator
     *
     * @return \Sulu\Component\Security\UserInterface
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set taxClass
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass
     * @return BaseProduct
     */
    public function setTaxClass(\Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass = null)
    {
        $this->taxClass = $taxClass;

        return $this;
    }

    /**
     * Get taxClass
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    public function getTaxClass()
    {
        return $this->taxClass;
    }

    /**
     * Add categories
     *
     * @param \Sulu\Bundle\CategoryBundle\Entity\Category $categories
     * @return BaseProduct
     */
    public function addCategory(\Sulu\Bundle\CategoryBundle\Entity\Category $categories)
    {
        $this->categories[] = $categories;
    
        return $this;
    }

    /**
     * Remove categories
     *
     * @param \Sulu\Bundle\CategoryBundle\Entity\Category $categories
     */
    public function removeCategory(\Sulu\Bundle\CategoryBundle\Entity\Category $categories)
    {
        $this->categories->removeElement($categories);
    }

    /**
     * Get categories
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Add media
     *
     * @param Media $media
     * @return Product
     */
    public function addMedia(Media $media)
    {
        $this->media[] = $media;

        return $this;
    }

    /**
     * Remove media
     *
     * @param Media $media
     */
    public function removeMedia(Media $media)
    {
        $this->media->removeElement($media);
    }

    /**
     * Get media
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * Set contentUnit
     *
     * @param Unit $contentUnit
     * @return BaseProduct
     */
    public function setContentUnit(Unit $contentUnit = null)
    {
        $this->contentUnit = $contentUnit;

        return $this;
    }

    /**
     * Get contentUnit
     *
     * @return Unit
     */
    public function getContentUnit()
    {
        return $this->contentUnit;
    }

    /**
     * Set orderUnit
     *
     * @param Unit $orderUnit
     * @return BaseProduct
     */
    public function setOrderUnit(Unit $orderUnit = null)
    {
        $this->orderUnit = $orderUnit;

        return $this;
    }

    /**
     * Get orderUnit
     *
     * @return Unit
     */
    public function getOrderUnit()
    {
        return $this->orderUnit;
    }

    /**
     * Set orderContentRatio
     *
     * @param string $orderContentRatio
     * @return BaseProduct
     */
    public function setOrderContentRatio($orderContentRatio)
    {
        $this->orderContentRatio = $orderContentRatio;

        return $this;
    }

    /**
     * Get orderContentRatio
     *
     * @return string
     */
    public function getOrderContentRatio()
    {
        return $this->orderContentRatio;
    }

    /**
     * Set minimumOrderQuantity
     *
     * @param float $minimumOrderQuantity
     * @return BaseProduct
     */
    public function setMinimumOrderQuantity($minimumOrderQuantity)
    {
        $this->minimumOrderQuantity = $minimumOrderQuantity;

        return $this;
    }

    /**
     * Get minimumOrderQuantity
     *
     * @return float
     */
    public function getMinimumOrderQuantity()
    {
        return $this->minimumOrderQuantity;
    }

    /**
     * Set recommendedOrderQuantity
     *
     * @param float $recommendedOrderQuantity
     * @return BaseProduct
     */
    public function setRecommendedOrderQuantity($recommendedOrderQuantity)
    {
        $this->recommendedOrderQuantity = $recommendedOrderQuantity;

        return $this;
    }

    /**
     * Get recommendedOrderQuantity
     *
     * @return float
     */
    public function getRecommendedOrderQuantity()
    {
        return $this->recommendedOrderQuantity;
    }
}
