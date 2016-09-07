<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Component\Security\Authentication\UserInterface;

/**
 * BaseProduct.
 */
abstract class BaseProduct implements ProductInterface
{
    // Product with variants
    const MASTER_PRODUCT = 2;
    // Product
    const SIMPLE_PRODUCT = 1;

    /**
     * @var string
     */
    protected $number;

    /**
     * @var int
     */
    protected $deliveryTime;

    /**
     * @var string
     */
    protected $globalTradeItemNumber;

    /**
     * @var string
     */
    protected $internalItemNumber;

    /**
     * @var bool
     */
    protected $isDeprecated = false;

    /**
     * @var string
     */
    protected $manufacturer;

    /**
     * @var float
     */
    protected $cost;

    /**
     * @var string
     */
    protected $priceInfo;

    /**
     * @var \DateTime
     */
    protected $created;

    /**
     * @var \DateTime
     */
    protected $changed;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $searchTerms;

    /**
     * @var \Sulu\Bundle\ContactBundle\Entity\Country
     */
    protected $manufacturerCountry;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Type
     */
    protected $type;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    protected $taxClass;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    protected $attributeSet;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Status
     */
    protected $status;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    protected $deliveryStatus;

    /**
     * @var AccountInterface
     */
    protected $supplier;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    protected $parent;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $sets;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $relations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $upsells;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $crosssells;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $categories;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    protected $media;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    protected $changer;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    protected $creator;

    /**
     * @var float
     */
    protected $minimumOrderQuantity;

    /**
     * @var float
     */
    protected $recommendedOrderQuantity;

    /**
     * @var float
     */
    protected $orderContentRatio;

    /**
     * @var Unit
     */
    protected $contentUnit;

    /**
     * @var Unit
     */
    protected $orderUnit;

    /**
     * @var bool
     */
    protected $areGrossPrices = false;

    /**
     * @var ArrayCollection
     */
    protected $tags;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->sets = new ArrayCollection();
        $this->relations = new ArrayCollection();
        $this->upsells = new ArrayCollection();
        $this->crosssells = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    /**
     * setId.
     *
     * @param int $id
     *
     * @return BaseProduct
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set number.
     *
     * @param string $number
     *
     * @return BaseProduct
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get number.
     *
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set globalTradeItemNumber.
     *
     * @param string $globalTradeItemNumber
     *
     * @return BaseProduct
     */
    public function setGlobalTradeItemNumber($globalTradeItemNumber)
    {
        $this->globalTradeItemNumber = $globalTradeItemNumber;

        return $this;
    }

    /**
     * Get globalTradeItemNumber.
     *
     * @return string
     */
    public function getGlobalTradeItemNumber()
    {
        return $this->globalTradeItemNumber;
    }

    /**
     * Set internalItemNumber.
     *
     * @param string $internalItemNumber
     *
     * @return BaseProduct
     */
    public function setInternalItemNumber($internalItemNumber)
    {
        $this->internalItemNumber = $internalItemNumber;

        return $this;
    }

    /**
     * Get internalItemNumber.
     *
     * @return string
     */
    public function getInternalItemNumber()
    {
        return $this->internalItemNumber;
    }

    /**
     * Set isDeprecated.
     *
     * @param bool $isDeprecated
     *
     * @return BaseProduct
     */
    public function setIsDeprecated($isDeprecated)
    {
        $this->isDeprecated = $isDeprecated;

        return $this;
    }

    /**
     * Get isDeprecated.
     *
     * @return bool
     */
    public function isDeprecated()
    {
        return $this->isDeprecated;
    }

    /**
     * Set manufacturer.
     *
     * @param string $manufacturer
     *
     * @return BaseProduct
     */
    public function setManufacturer($manufacturer)
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * Get manufacturer.
     *
     * @return string
     */
    public function getManufacturer()
    {
        return $this->manufacturer;
    }

    /**
     * Set cost.
     *
     * @param float $cost
     *
     * @return BaseProduct
     */
    public function setCost($cost)
    {
        $this->cost = $cost;

        return $this;
    }

    /**
     * Get cost.
     *
     * @return float
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     * Set priceInfo.
     *
     * @param string $priceInfo
     *
     * @return BaseProduct
     */
    public function setPriceInfo($priceInfo)
    {
        $this->priceInfo = $priceInfo;

        return $this;
    }

    /**
     * Get priceInfo.
     *
     * @return string
     */
    public function getPriceInfo()
    {
        return $this->priceInfo;
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return BaseProduct
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set changed.
     *
     * @param \DateTime $changed
     *
     * @return BaseProduct
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;

        return $this;
    }

    /**
     * Get changed.
     *
     * @return \DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set manufacturerCountry.
     *
     * @param \Sulu\Bundle\ContactBundle\Entity\Country $manufacturerCountry
     *
     * @return BaseProduct
     */
    public function setManufacturerCountry(\Sulu\Bundle\ContactBundle\Entity\Country $manufacturerCountry = null)
    {
        $this->manufacturerCountry = $manufacturerCountry;

        return $this;
    }

    /**
     * Get manufacturerCountry.
     *
     * @return \Sulu\Bundle\ContactBundle\Entity\Country
     */
    public function getManufacturerCountry()
    {
        return $this->manufacturerCountry;
    }

    /**
     * Set type.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Type $type
     *
     * @return BaseProduct
     */
    public function setType(\Sulu\Bundle\ProductBundle\Entity\Type $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set attributeSet.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeSet $attributeSet
     *
     * @return BaseProduct
     */
    public function setAttributeSet(\Sulu\Bundle\ProductBundle\Entity\AttributeSet $attributeSet)
    {
        $this->attributeSet = $attributeSet;

        return $this;
    }

    /**
     * Get attributeSet.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }

    /**
     * Set status.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Status $status
     *
     * @return BaseProduct
     */
    public function setStatus(\Sulu\Bundle\ProductBundle\Entity\Status $status = null)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set deliveryStatus.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus
     *
     * @return BaseProduct
     */
    public function setDeliveryStatus(\Sulu\Bundle\ProductBundle\Entity\DeliveryStatus $deliveryStatus = null)
    {
        $this->deliveryStatus = $deliveryStatus;

        return $this;
    }

    /**
     * Get deliveryStatus.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\DeliveryStatus
     */
    public function getDeliveryStatus()
    {
        return $this->deliveryStatus;
    }

    /**
     * Set supplier.
     *
     * @param AccountInterface $supplier
     *
     * @return BaseProduct
     */
    public function setSupplier(AccountInterface $supplier = null)
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * Get supplier.
     *
     * @return AccountInterface
     */
    public function getSupplier()
    {
        return $this->supplier;
    }

    /**
     * Set parent.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $parent
     *
     * @return BaseProduct
     */
    public function setParent(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add sets.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets
     *
     * @return BaseProduct
     */
    public function addSet(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets)
    {
        $this->sets[] = $sets;

        return $this;
    }

    /**
     * Remove sets.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets
     */
    public function removeSet(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $sets)
    {
        $this->sets->removeElement($sets);
    }

    /**
     * Get sets.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSets()
    {
        return $this->sets;
    }

    /**
     * Add relations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations
     *
     * @return BaseProduct
     */
    public function addRelation(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations)
    {
        $this->relations[] = $relations;

        return $this;
    }

    /**
     * Remove relations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations
     */
    public function removeRelation(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $relations)
    {
        $this->relations->removeElement($relations);
    }

    /**
     * Get relations.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Add upsells.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells
     *
     * @return BaseProduct
     */
    public function addUpsell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells)
    {
        $this->upsells[] = $upsells;

        return $this;
    }

    /**
     * Remove upsells.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells
     */
    public function removeUpsell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $upsells)
    {
        $this->upsells->removeElement($upsells);
    }

    /**
     * Get upsells.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUpsells()
    {
        return $this->upsells;
    }

    /**
     * Add crosssells.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells
     *
     * @return BaseProduct
     */
    public function addCrosssell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells)
    {
        $this->crosssells[] = $crosssells;

        return $this;
    }

    /**
     * Remove crosssells.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells
     */
    public function removeCrosssell(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $crosssells)
    {
        $this->crosssells->removeElement($crosssells);
    }

    /**
     * Get crosssells.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCrosssells()
    {
        return $this->crosssells;
    }

    /**
     * Set changer.
     *
     * @param \Sulu\Component\Security\Authentication\UserInterface $changer
     *
     * @return BaseProduct
     */
    public function setChanger(UserInterface $changer = null)
    {
        $this->changer = $changer;

        return $this;
    }

    /**
     * Get changer.
     *
     * @return \Sulu\Component\Security\Authentication\UserInterface
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator.
     *
     * @param \Sulu\Component\Security\Authentication\UserInterface $creator
     *
     * @return BaseProduct
     */
    public function setCreator(UserInterface $creator = null)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator.
     *
     * @return \Sulu\Component\Security\Authentication\UserInterface
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set taxClass.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass
     *
     * @return BaseProduct
     */
    public function setTaxClass(\Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass = null)
    {
        $this->taxClass = $taxClass;

        return $this;
    }

    /**
     * Get taxClass.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    public function getTaxClass()
    {
        return $this->taxClass;
    }

    /**
     * Add categories.
     *
     * @param \Sulu\Bundle\CategoryBundle\Entity\Category $categories
     *
     * @return BaseProduct
     */
    public function addCategory(\Sulu\Bundle\CategoryBundle\Entity\Category $categories)
    {
        $this->categories[] = $categories;

        return $this;
    }

    /**
     * Remove categories.
     *
     * @param \Sulu\Bundle\CategoryBundle\Entity\Category $categories
     */
    public function removeCategory(\Sulu\Bundle\CategoryBundle\Entity\Category $categories)
    {
        $this->categories->removeElement($categories);
    }

    /**
     * Get categories.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Add media.
     *
     * @param Media $media
     *
     * @return Product
     */
    public function addMedia(Media $media)
    {
        $this->media[] = $media;

        return $this;
    }

    /**
     * Remove media.
     *
     * @param Media $media
     */
    public function removeMedia(Media $media)
    {
        $this->media->removeElement($media);
    }

    /**
     * Get media.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * Set contentUnit.
     *
     * @param Unit $contentUnit
     *
     * @return BaseProduct
     */
    public function setContentUnit(Unit $contentUnit = null)
    {
        $this->contentUnit = $contentUnit;

        return $this;
    }

    /**
     * Get contentUnit.
     *
     * @return Unit
     */
    public function getContentUnit()
    {
        return $this->contentUnit;
    }

    /**
     * Set orderUnit.
     *
     * @param Unit $orderUnit
     *
     * @return BaseProduct
     */
    public function setOrderUnit(Unit $orderUnit = null)
    {
        $this->orderUnit = $orderUnit;

        return $this;
    }

    /**
     * Get orderUnit.
     *
     * @return Unit
     */
    public function getOrderUnit()
    {
        return $this->orderUnit;
    }

    /**
     * @param float $orderContentRatio
     *
     * @return BaseProduct
     */
    public function setOrderContentRatio($orderContentRatio)
    {
        $this->orderContentRatio = $orderContentRatio;

        return $this;
    }

    /**
     * @return float
     */
    public function getOrderContentRatio()
    {
        return $this->orderContentRatio;
    }

    /**
     * Set minimumOrderQuantity.
     *
     * @param float $minimumOrderQuantity
     *
     * @return BaseProduct
     */
    public function setMinimumOrderQuantity($minimumOrderQuantity)
    {
        $this->minimumOrderQuantity = $minimumOrderQuantity;

        return $this;
    }

    /**
     * Get minimumOrderQuantity.
     *
     * @return float
     */
    public function getMinimumOrderQuantity()
    {
        return $this->minimumOrderQuantity;
    }

    /**
     * Set recommendedOrderQuantity.
     *
     * @param float $recommendedOrderQuantity
     *
     * @return BaseProduct
     */
    public function setRecommendedOrderQuantity($recommendedOrderQuantity)
    {
        $this->recommendedOrderQuantity = $recommendedOrderQuantity;

        return $this;
    }

    /**
     * Get recommendedOrderQuantity.
     *
     * @return float
     */
    public function getRecommendedOrderQuantity()
    {
        return $this->recommendedOrderQuantity;
    }

    /**
     * Get deliveryTime.
     *
     * @return int
     */
    public function getDeliveryTime()
    {
        return $this->deliveryTime;
    }

    /**
     * Set deliveryTime.
     *
     * @param int $deliveryTime
     *
     * @return BaseProduct
     */
    public function setDeliveryTime($deliveryTime)
    {
        $this->deliveryTime = $deliveryTime;

        return $this;
    }

    /**
     * Set areGrossPrices.
     *
     * @param bool $areGrossPrices
     *
     * @return BaseProduct
     */
    public function setAreGrossPrices($areGrossPrices)
    {
        $this->areGrossPrices = $areGrossPrices;

        return $this;
    }

    /**
     * Get areGrossPrices.
     *
     * @return bool
     */
    public function getAreGrossPrices()
    {
        return $this->areGrossPrices;
    }

    /**
     * @return string
     */
    public function getSearchTerms()
    {
        return $this->searchTerms;
    }

    /**
     * @param string $searchTerms
     *
     * @return BaseProduct
     */
    public function setSearchTerms($searchTerms)
    {
        $this->searchTerms = $searchTerms;

        return $this;
    }

    /**
     * Helper method to check if the product is
     * a valid shop product.
     *
     * @param string $defaultCurrency
     *
     * @return bool
     */
    public function isValidShopProduct($defaultCurrency)
    {
        $isValid = false;

        if (method_exists($this, 'getPrices') &&
            $this->getStatus()->getId() == Status::ACTIVE &&
            $this->getPrices() &&
            count($this->getPrices()) > 0 &&
            $this->hasPriceInDefaultCurrency($this->getPrices(), $defaultCurrency) &&
            $this->getSupplier()
        ) {
            $isValid = true;
        }

        return $isValid;
    }

    /**
     * Checks if price in default currency exists.
     *
     * @param ProductPrice[] $prices
     * @param string $defaultCurrency
     *
     * @return bool
     */
    private function hasPriceInDefaultCurrency($prices, $defaultCurrency)
    {
        foreach ($prices as $price) {
            if ($price->getCurrency()->getCode() === $defaultCurrency) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagNameArray()
    {
        $tags = [];

        if (!is_null($this->getTags())) {
            foreach ($this->getTags() as $tag) {
                $tags[] = $tag->getName();
            }
        }

        return $tags;
    }

    /**
     * @return ArrayCollection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param Tag $tag
     *
     * @return self
     */
    public function addTag(Tag $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * @param Tag $tag
     *
     * @return self
     */
    public function removeTag(Tag $tag)
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
