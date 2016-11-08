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
use Doctrine\Common\Collections\Collection;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\ContactBundle\Entity\Country;
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
     * @var Country
     */
    protected $manufacturerCountry;

    /**
     * @var Type
     */
    protected $type;

    /**
     * @var TaxClass
     */
    protected $taxClass;

    /**
     * @var AttributeSet
     */
    protected $attributeSet;

    /**
     * @var Status
     */
    protected $status;

    /**
     * @var DeliveryStatus
     */
    protected $deliveryStatus;

    /**
     * @var AccountInterface
     */
    protected $supplier;

    /**
     * @var ProductInterface
     */
    protected $parent;

    /**
     * @var Collection
     */
    protected $sets;

    /**
     * @var Collection
     */
    protected $relations;

    /**
     * @var Collection
     */
    protected $upsells;

    /**
     * @var Collection
     */
    protected $crosssells;

    /**
     * @var Collection
     */
    protected $categories;

    /**
     * @var Collection
     */
    protected $media;

    /**
     * @var UserInterface
     */
    protected $changer;

    /**
     * @var UserInterface
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
     * @var Collection
     */
    protected $tags;

    /**
     * @var int
     */
    protected $numberOfVariants = 0;

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
     * @param Country $manufacturerCountry
     *
     * @return BaseProduct
     */
    public function setManufacturerCountry(Country $manufacturerCountry = null)
    {
        $this->manufacturerCountry = $manufacturerCountry;

        return $this;
    }

    /**
     * Get manufacturerCountry.
     *
     * @return Country
     */
    public function getManufacturerCountry()
    {
        return $this->manufacturerCountry;
    }

    /**
     * Set type.
     *
     * @param Type $type
     *
     * @return BaseProduct
     */
    public function setType(Type $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set attributeSet.
     *
     * @param AttributeSet $attributeSet
     *
     * @return BaseProduct
     */
    public function setAttributeSet(AttributeSet $attributeSet)
    {
        $this->attributeSet = $attributeSet;

        return $this;
    }

    /**
     * Get attributeSet.
     *
     * @return AttributeSet
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }

    /**
     * Set status.
     *
     * @param Status $status
     *
     * @return BaseProduct
     */
    public function setStatus(Status $status = null)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return Status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set deliveryStatus.
     *
     * @param DeliveryStatus $deliveryStatus
     *
     * @return BaseProduct
     */
    public function setDeliveryStatus(DeliveryStatus $deliveryStatus = null)
    {
        $this->deliveryStatus = $deliveryStatus;

        return $this;
    }

    /**
     * Get deliveryStatus.
     *
     * @return DeliveryStatus
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
     * @param ProductInterface $parent
     *
     * @return BaseProduct
     */
    public function setParent(ProductInterface $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return ProductInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add sets.
     *
     * @param ProductInterface $sets
     *
     * @return BaseProduct
     */
    public function addSet(ProductInterface $sets)
    {
        $this->sets[] = $sets;

        return $this;
    }

    /**
     * Remove sets.
     *
     * @param ProductInterface $sets
     */
    public function removeSet(ProductInterface $sets)
    {
        $this->sets->removeElement($sets);
    }

    /**
     * Get sets.
     *
     * @return Collection
     */
    public function getSets()
    {
        return $this->sets;
    }

    /**
     * Add relations.
     *
     * @param ProductInterface $relations
     *
     * @return BaseProduct
     */
    public function addRelation(ProductInterface $relations)
    {
        $this->relations[] = $relations;

        return $this;
    }

    /**
     * Remove relations.
     *
     * @param ProductInterface $relations
     */
    public function removeRelation(ProductInterface $relations)
    {
        $this->relations->removeElement($relations);
    }

    /**
     * Get relations.
     *
     * @return Collection
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Add upsells.
     *
     * @param ProductInterface $upsells
     *
     * @return BaseProduct
     */
    public function addUpsell(ProductInterface $upsells)
    {
        $this->upsells[] = $upsells;

        return $this;
    }

    /**
     * Remove upsells.
     *
     * @param ProductInterface $upsells
     */
    public function removeUpsell(ProductInterface $upsells)
    {
        $this->upsells->removeElement($upsells);
    }

    /**
     * Get upsells.
     *
     * @return Collection
     */
    public function getUpsells()
    {
        return $this->upsells;
    }

    /**
     * Add crosssells.
     *
     * @param ProductInterface $crosssells
     *
     * @return BaseProduct
     */
    public function addCrosssell(ProductInterface $crosssells)
    {
        $this->crosssells[] = $crosssells;

        return $this;
    }

    /**
     * Remove crosssells.
     *
     * @param ProductInterface $crosssells
     */
    public function removeCrosssell(ProductInterface $crosssells)
    {
        $this->crosssells->removeElement($crosssells);
    }

    /**
     * Get crosssells.
     *
     * @return Collection
     */
    public function getCrosssells()
    {
        return $this->crosssells;
    }

    /**
     * Set changer.
     *
     * @param UserInterface $changer
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
     * @return UserInterface
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator.
     *
     * @param UserInterface $creator
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
     * @return UserInterface
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set taxClass.
     *
     * @param TaxClass $taxClass
     *
     * @return BaseProduct
     */
    public function setTaxClass(TaxClass $taxClass = null)
    {
        $this->taxClass = $taxClass;

        return $this;
    }

    /**
     * Get taxClass.
     *
     * @return TaxClass
     */
    public function getTaxClass()
    {
        return $this->taxClass;
    }

    /**
     * Add categories.
     *
     * @param Category $categories
     *
     * @return BaseProduct
     */
    public function addCategory(Category $categories)
    {
        $this->categories[] = $categories;

        return $this;
    }

    /**
     * Remove categories.
     *
     * @param Category $categories
     */
    public function removeCategory(Category $categories)
    {
        $this->categories->removeElement($categories);
    }

    /**
     * Get categories.
     *
     * @return Collection
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
     * @return Collection
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
     * @return Collection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param Tag $tag
     *
     * @return $this
     */
    public function addTag(Tag $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * @param Tag $tag
     *
     * @return $this
     */
    public function removeTag(Tag $tag)
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfVariants()
    {
        return $this->numberOfVariants;
    }

    /**
     * @param int $numberOfVariants
     *
     * @return $this
     */
    public function setNumberOfVariants($numberOfVariants)
    {
        $this->numberOfVariants = $numberOfVariants;

        return $this;
    }
}
