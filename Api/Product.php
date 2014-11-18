<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Sulu\Bundle\CategoryBundle\Api\Category;
use Sulu\Bundle\CategoryBundle\Entity\Category as CategoryEntity;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\MediaBundle\Entity\Media as MediaEntity;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute as ProductAttributeEntity;
use Sulu\Bundle\ProductBundle\Api\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface as Entity;
use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\UserInterface;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet as AttributeSetEntity;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice as ProductPriceEntity;
use Sulu\Bundle\ProductBundle\Entity\Status as StatusEntity;
use Sulu\Bundle\ProductBundle\Entity\Type as TypeEntity;
use Sulu\Bundle\ProductBundle\Entity\TaxClass as TaxClassEntity;
use Hateoas\Configuration\Annotation\Relation;
use Sulu\Bundle\ProductBundle\Api\Unit;
use Sulu\Bundle\ProductBundle\Entity\Unit as UnitEntity;

/**
 * The product class which will be exported to the API
 * @package Sulu\Bundle\ProductBundle\Api
 * @Relation("self", href="expr('/api/admin/products/' ~ object.getId())")
 */
class Product extends ApiWrapper
{
    /**
     * @var Array
     */
    private $media;

    /**
     * @param Entity $product The product to wrap
     * @param string $locale The locale of this product
     */
    public function __construct(Entity $product, $locale)
    {
        $this->entity = $product;
        $this->locale = $locale;
    }

    /**
     * Returns the id of the product
     * @return int
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the product
     * @return string The name of the product
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the product
     * @param string $name The name of the product
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * Returns the deprecated state of a product
     * @return boolean
     * @VirtualProperty
     * @SerializedName("isDeprecated")
     */
    public function isDeprecated()
    {
        return $this->entity->isDeprecated();
    }

    /**
     * Sets the deprecated state of the product
     * @param boolean $isDeprecated
     */
    public function setIsDeprecated($isDeprecated)
    {
        $this->entity->setIsDeprecated($isDeprecated);
    }

    /**
     * Set minimumOrderQuantity
     *
     * @param float $minimumOrderQuantity
     */
    public function setMinimumOrderQuantity($minimumOrderQuantity)
    {
        $this->entity->setMinimumOrderQuantity($minimumOrderQuantity);
    }

    /**
     * Get minimumOrderQuantity
     *
     * @return float
     * @VirtualProperty
     * @SerializedName("minimumOrderQuantity")
     */
    public function getMinimumOrderQuantity()
    {
        return $this->entity->getMinimumOrderQuantity();
    }

    /**
     * Set recommendedOrderQuantity
     *
     * @param float $recommendedOrderQuantity
     */
    public function setRecommendedOrderQuantity($recommendedOrderQuantity)
    {
        $this->entity->setRecommendedOrderQuantity($recommendedOrderQuantity);
    }

    /**
     * Get recommendedOrderQuantity
     *
     * @return float
     * @VirtualProperty
     * @SerializedName("recommendedOrderQuantity")
     */
    public function getRecommendedOrderQuantity()
    {
        return $this->entity->getRecommendedOrderQuantity();
    }

    /**
     * Set orderContentRatio
     *
     * @param float $orderContentRatio
     */
    public function setOrderContentRatio($orderContentRatio)
    {
        $this->entity->orderContentRatio($orderContentRatio);
    }

    /**
     * Get orderContentRatio
     *
     * @return float
     * @VirtualProperty
     * @SerializedName("orderContentRatio")
     */
    public function getOrderContentRatio()
    {
        return $this->entity->getOrderContentRatio();
    }

    /**
     * Returns the short description of the product
     * @return string The short description of the product
     * @VirtualProperty
     * @SerializedName("shortDescription")
     */
    public function getShortDescription()
    {
        return $this->getTranslation()->getShortDescription();
    }

    /**
     * Sets the short description of the product
     * @param string $shortDescription The short description of the product
     */
    public function setShortDescription($shortDescription)
    {
        $this->getTranslation()->setShortDescription($shortDescription);
    }

    /**
     * Returns the long description of the product
     * @return string The long description of the product
     * @VirtualProperty
     * @SerializedName("longDescription")
     */
    public function getLongDescription()
    {
        return $this->getTranslation()->getLongDescription();
    }

    /**
     * Sets the long description of the product
     * @param string $longDescription The short description of the product
     */
    public function setLongDescription($longDescription)
    {
        $this->getTranslation()->setLongDescription($longDescription);
    }

    /**
     * Returns the number of the product
     * @return string The number of the product
     * @VirtualProperty
     * @SerializedName("number")
     */
    public function getNumber()
    {
        return $this->entity->getNumber();
    }

    /**
     * Sets the number of the product
     * @param string $number The number of the product
     */
    public function setNumber($number)
    {
        $this->entity->setNumber($number);
    }

    /**
     * Returns the globalTradeItemNumber of the product
     * @return string The globalTradeItemNumber of the product
     * @VirtualProperty
     * @SerializedName("globalTradeItemNumber")
     */
    public function getGlobalTradeItemNumber()
    {
        return $this->entity->getGlobalTradeItemNumber();
    }

    /**
     * Sets the globalTradeItemNumber of the product
     * @param string $globalTradeItemNumber The globalTradeItemNumber of the product
     */
    public function setGlobalTradeItemNumber($globalTradeItemNumber)
    {
        $this->entity->setGlobalTradeItemNumber($globalTradeItemNumber);
    }

    /**
     * Returns the internalItemNumber of the product
     * @return string The internalItemNumber of the product
     * @VirtualProperty
     * @SerializedName("internalItemNumber")
     */
    public function getInternalItemNumber()
    {
        return $this->entity->getInternalItemNumber();
    }

    /**
     * Sets the internalItemNumber of the product
     * @param string $internalItemNumber The number of the product
     */
    public function setInternalItemNumber($internalItemNumber)
    {
        $this->entity->setInternalItemNumber($internalItemNumber);
    }

    /**
     * Sets the supplier of the product
     * @param Sulu\Bundle\ContactBundle\Entity\Account $supplier
     */
    public function setSupplier($supplier)
    {
        $this->entity->setSupplier($supplier);
    }

    /**
     * Returns the cost of the product
     * @return double The cost of the product
     * @VirtualProperty
     * @SerializedName("cost")
     */
    public function getCost()
    {
        return $this->entity->getCost();
    }

    /**
     * Sets the cost of the product
     * @param double $cost The cost of the product
     */
    public function setCost($cost)
    {
        $this->entity->setCost($cost);
    }

    /**
     * Sets the priceinfo of the product
     * @param string $priceInfo The cost of the product
     */
    public function setPriceInfo($priceInfo)
    {
        $this->entity->setPriceInfo($priceInfo);
    }

    /**
     * Returns the cost of the product
     * @return double The cost of the product
     * @VirtualProperty
     * @SerializedName("priceInfo")
     */
    public function getPriceInfo()
    {
        return $this->entity->getPriceInfo();
    }

    /**
     * Returns the manufacturer of the product
     * @return string The manufacturer of the product
     * @VirtualProperty
     * @SerializedName("manufacturer")
     */
    public function getManufacturer()
    {
        return $this->entity->getManufacturer();
    }

    /**
     * Sets the manufacturer of the product
     * @param string $manufacturer The manufacturer of the product
     */
    public function setManufacturer($manufacturer)
    {
        $this->entity->setManufacturer($manufacturer);
    }

    /**
     * Returns the parent of the product
     * @return Product The parent of the product
     * @VirtualProperty
     * @SerializedName("parent")
     */
    public function getParent()
    {
        $parent = $this->entity->getParent();

        if ($parent) {
            return new Product($parent, $this->locale);
        } else {
            return null;
        }
    }

    /**
     * Sets the parent of the product
     * @param Product $parent The parent of the product
     */
    public function setParent(Product $parent = null)
    {
        if ($parent != null) {
            $this->entity->setParent($parent->getEntity());
        } else {
            $this->entity->setParent(null);
        }
    }

    /**
     * Returns the children of the product
     * @return ProductInterface[]
     */
    public function getChildren()
    {
        return $this->entity->getChildren();
    }

    /**
     * Adds a product attribute to the product
     * @param ProductAttributeEntity $productAttribute
     */
    public function addProductAttribute(ProductAttributeEntity $productAttribute)
    {
        $this->entity->addProductAttribute($productAttribute);
    }

    /**
     * Returns the type of the product
     * @return Type The type of the product
     * @VirtualProperty
     * @SerializedName("type")
     */
    public function getType()
    {
        return new Type($this->entity->getType(), $this->locale);
    }

    /**
     * Sets the type of the product
     * @param TypeEntity $type The type of the product
     */
    public function setType(TypeEntity $type)
    {
        $this->entity->setType($type);
    }

    /**
     * Returns the status of the product
     * @return Status The status of the product
     * @VirtualProperty
     * @SerializedName("status")
     */
    public function getStatus()
    {
        return new Status($this->entity->getStatus(), $this->locale);
    }

    /**
     * Sets the status of the product
     * @param StatusEntity $status The status of the product
     */
    public function setStatus(StatusEntity $status)
    {
        $this->entity->setStatus($status);
    }

    /**
     * Returns the orderUnit of the product
     * @return Unit
     * @VirtualProperty
     * @SerializedName("orderUnit")
     */
    public function getOrderUnit()
    {
        return new Unit($this->entity->getOrderUnit(), $this->locale);
    }

    /**
     * Sets the order unit of the product
     *
     * @param UnitEntity $unit
     */
    public function setOrderUnit(UnitEntity $unit = null)
    {
        $this->entity->setOrderUnit($unit);
    }

    /**
     * Returns the contentUnit of the product
     * @return Unit
     * @VirtualProperty
     * @SerializedName("contentUnit")
     */
    public function getContentUnit()
    {
        return new Unit($this->entity->getContentUnit(), $this->locale);
    }

    /**
     * Sets the order contentUnit of the product
     *
     * @param UnitEntity $unit
     */
    public function setContentUnit(UnitEntity $unit = null)
    {
        $this->entity->setContentUnit($unit);
    }

    /**
     * Returns the tax class of the product
     * @return TaxClass The status of the product
     * @VirtualProperty
     * @SerializedName("taxClass")
     */
    public function getTaxClass()
    {
        $taxClass = $this->entity->getTaxClass();
        if ($taxClass) {
            return new TaxClass($this->entity->getTaxClass(), $this->locale);
        } else {
            return null;
        }
    }

    /**
     * Sets the tax class of the product
     * @param TaxClassEntity $taxClass The tax class of the product
     */
    public function setTaxClass(TaxClassEntity $taxClass)
    {
        $this->entity->setTaxClass($taxClass);
    }

    /**
     * Returns the attribute set of the product
     * @return AttributeSet The attribute set of the product
     * @VirtualProperty
     * @SerializedName("attributeSet")
     */
    public function getAttributeSet()
    {
        $attributeSet = $this->entity->getAttributeSet();
        if ($attributeSet) {
            return new AttributeSet($attributeSet, $this->locale);
        } else {
            return null;
        }
    }

    /**
     * Sets the attribute set of the product
     * @param AttributeSetEntity $attributeSet The attribute set of the product
     */
    public function setAttributeSet(AttributeSetEntity $attributeSet)
    {
        $this->entity->setAttributeSet($attributeSet);
    }

    /**
     * Removes the given price from the product
     * @param ProductPriceEntity $price
     */
    public function removePrice(ProductPriceEntity $price)
    {
        $this->entity->removePrice($price);
    }

    /**
     * Returns the prices for the product
     * @return \Sulu\Bundle\ProductBundle\Api\ProductPrice[]
     * @VirtualProperty
     * @SerializedName("prices")
     */
    public function getPrices()
    {
        $priceEntities = $this->entity->getPrices();

        $prices = array();
        foreach ($priceEntities as $priceEntity) {
            $prices[] = new ProductPrice($priceEntity, $this->locale);
        }

        return $prices;
    }

    /**
     * Returns the attributes for the product
     * @return \Sulu\Bundle\ProductBundle\Api\ProductAttributes[]
     * @VirtualProperty
     * @SerializedName("attributes")
     */
    public function getAttributes()
    {
        $attributeEntities = $this->entity->getProductAttributes();

        $attributes = array();
        foreach ($attributeEntities as $attributesEntity) {
            $attributes[] = new ProductAttribute($attributesEntity, $this->locale);
        }

        return $attributes;
    }

    /**
     * Adds a category to the product
     * @param CategoryEntity $category
     */
    public function addCategory(CategoryEntity $category)
    {
        $this->entity->addCategory($category);
    }

    /**
     * Removes a category from the product
     * @param CategoryEntity $category
     */
    public function removeCategory(CategoryEntity $category)
    {
        $this->entity->removeCategory($category);
    }

    /**
     * Returns the categories for the product
     * @return CategoryEntity[]
     * @VirtualProperty
     * @SerializedName("categories")
     */
    public function getCategories()
    {
        $categoryEntities = $this->entity->getCategories();

        $categories = array();
        if ($categoryEntities) {
            foreach ($categoryEntities as $categoryEntity) {
                $categories[] = new Category($categoryEntity, $this->locale);
            }
        }

        return $categories;
    }

    /**
     * Sets the changer of the product
     * @param UserInterface $user The changer of the product
     */
    public function setChanger(UserInterface $user)
    {
        $this->entity->setChanger($user);
    }

    /**
     * Sets the creator of the product
     * @param UserInterface $user The creator of the product
     */
    public function setCreator(UserInterface $user)
    {
        $this->entity->setCreator($user);
    }

    /**
     * Returns the creator of the product
     * @return creator/owner of the product
     */
    public function getCreator()
    {
        return $this->entity->getCreator();
    }

    /**
     * Sets the change time of the product
     * @param \DateTime $changed
     */
    public function setChanged(\DateTime $changed)
    {
        $this->entity->setChanged($changed);
    }

    /**
     * Sets the created time of the product
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created)
    {
        $this->entity->setCreated($created);
    }

    private function getTranslation()
    {
        $productTranslation = $this->entity->getTranslation($this->locale);
        if (!$productTranslation) {
            $productTranslation = new ProductTranslation();
            $productTranslation->setLocale($this->locale);
            $productTranslation->setProduct($this->entity);

            $this->entity->addTranslation($productTranslation);
        }

        return $productTranslation;
    }

    /**
     * Adds a media to the product
     *
     * @param Media $media
     */
    public function addMedia(Media $media)
    {
        $this->entity->addMedia($media->getEntity());
    }

    /**
     * Removes a media from the product
     *
     * @param Media $media
     */
    public function removeMedia(Media $media)
    {
        $this->entity->removeMedia($media->getEntity());
    }

    public function setMedia($media)
    {
        $this->media = $media;
    }

    /**
     * Returns the media for the product
     *
     * @return Media[]
     * @VirtualProperty
     * @SerializedName("media")
     */
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * Returns true when collection of media contains media with specific id
     *
     * @param Media $media
     * @return bool
     */
    public function containsMedia(Media $media)
    {
        return $this->entity->getMedia()->contains($media->getEntity());
    }
}
