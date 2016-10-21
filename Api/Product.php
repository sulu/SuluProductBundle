<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Hateoas\Configuration\Annotation\Relation;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Sulu\Bundle\CategoryBundle\Api\Category;
use Sulu\Bundle\CategoryBundle\Entity\Category as CategoryEntity;
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\ProductBundle\Entity\Addon as AddonEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet as AttributeSetEntity;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus as DeliveryStatusEntity;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute as ProductAttributeEntity;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface as Entity;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice as ProductPriceEntity;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice as SpecialPriceEntity;
use Sulu\Bundle\ProductBundle\Entity\Status as StatusEntity;
use Sulu\Bundle\ProductBundle\Entity\TaxClass as TaxClassEntity;
use Sulu\Bundle\ProductBundle\Entity\Type as TypeEntity;
use Sulu\Bundle\ProductBundle\Entity\Unit as UnitEntity;
use Sulu\Bundle\ProductBundle\Product\ProductLocaleManager;
use Sulu\Bundle\ProductBundle\Util\PriceFormatter;
use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\Authentication\UserInterface;

/**
 * The product class which will be exported to the API.
 *
 * @Relation("self", href="expr('/api/admin/products/' ~ object.getId())")
 * @ExclusionPolicy("all")
 */
class Product extends ApiWrapper implements ApiProductInterface
{
    /**
     * @var array
     */
    private $media;

    /**
     * @var AccountManager
     */
    protected $accountManager;

    /**
     * @var PriceFormatter
     */
    protected $priceFormatter;

    /**
     * @var ProductLocaleManager
     */
    protected $productLocaleManager;

    /**
     * @param Entity $product The product to wrap
     * @param string $locale The locale of this product
     * @param PriceFormatter $priceFormatter
     * @param ProductLocaleManager $productLocaleManager
     * @param AccountManager|null $accountManager
     */
    public function __construct(
        Entity $product,
        $locale,
        PriceFormatter $priceFormatter,
        ProductLocaleManager $productLocaleManager,
        AccountManager $accountManager = null
    ) {
        $this->entity = $product;
        $this->priceFormatter = $priceFormatter;
        $this->productLocaleManager = $productLocaleManager;
        $this->accountManager = $accountManager;

        $this->locale = $locale;
        if (!$productLocaleManager->isLocaleConfigured($locale)) {
            $this->locale = $productLocaleManager->getFallbackLocale();
        }
    }

    /**
     * Returns the id of the product.
     *
     * @VirtualProperty
     * @SerializedName("id")
     * @Groups({"Default","cart"})
     *
     * @return int
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the product.
     *
     * @VirtualProperty
     * @SerializedName("name")
     * @Groups({"cart"})
     *
     * @return string The name of the product
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the product.
     *
     * @param string $name The name of the product
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * Returns the deprecated state of a product.
     *
     * @VirtualProperty
     * @SerializedName("isDeprecated")
     *
     * @return bool
     */
    public function isDeprecated()
    {
        return $this->entity->isDeprecated();
    }

    /**
     * Sets the deprecated state of the product.
     *
     * @param bool $isDeprecated
     */
    public function setIsDeprecated($isDeprecated)
    {
        $this->entity->setIsDeprecated($isDeprecated);
    }

    /**
     * @param float $minimumOrderQuantity
     */
    public function setMinimumOrderQuantity($minimumOrderQuantity)
    {
        $this->entity->setMinimumOrderQuantity($minimumOrderQuantity);
    }

    /**
     * @VirtualProperty
     * @SerializedName("minimumOrderQuantity")
     * @Groups({"cart"})
     *
     * @return float
     */
    public function getMinimumOrderQuantity()
    {
        return $this->entity->getMinimumOrderQuantity();
    }

    /**
     * @param int $deliveryTime
     */
    public function setDeliveryTime($deliveryTime)
    {
        $this->entity->setDeliveryTime($deliveryTime);
    }

    /**
     * @VirtualProperty
     * @SerializedName("deliveryTime")
     * @Groups({"cart"})
     *
     * @return int
     */
    public function getDeliveryTime()
    {
        return $this->entity->getDeliveryTime();
    }

    /**
     * @param float $recommendedOrderQuantity
     */
    public function setRecommendedOrderQuantity($recommendedOrderQuantity)
    {
        $this->entity->setRecommendedOrderQuantity($recommendedOrderQuantity);
    }

    /**
     * @VirtualProperty
     * @SerializedName("recommendedOrderQuantity")
     * @Groups({"cart"})
     *
     * @return float
     */
    public function getRecommendedOrderQuantity()
    {
        return $this->entity->getRecommendedOrderQuantity();
    }

    /**
     * @param float $orderContentRatio
     */
    public function setOrderContentRatio($orderContentRatio)
    {
        $this->entity->setOrderContentRatio($orderContentRatio);
    }

    /**
     * @VirtualProperty
     * @SerializedName("orderContentRatio")
     *
     * @return float
     */
    public function getOrderContentRatio()
    {
        return $this->entity->getOrderContentRatio();
    }

    /**
     * Returns the short description of the product.
     *
     * @VirtualProperty
     * @SerializedName("shortDescription")
     *
     * @return string The short description of the product
     */
    public function getShortDescription()
    {
        return $this->getTranslation()->getShortDescription();
    }

    /**
     * Sets the short description of the product.
     *
     * @param string $shortDescription The short description of the product
     */
    public function setShortDescription($shortDescription)
    {
        $this->getTranslation()->setShortDescription($shortDescription);
    }

    /**
     * Returns the long description of the product.
     *
     * @VirtualProperty
     * @SerializedName("longDescription")
     *
     * @return string The long description of the product
     */
    public function getLongDescription()
    {
        return $this->getTranslation()->getLongDescription();
    }

    /**
     * Sets the long description of the product.
     *
     * @param string $longDescription The short description of the product
     */
    public function setLongDescription($longDescription)
    {
        $this->getTranslation()->setLongDescription($longDescription);
    }

    /**
     * Returns the number of the product.
     *
     * @VirtualProperty
     * @SerializedName("number")
     * @Groups({"cart"})
     *
     * @return string The number of the product
     */
    public function getNumber()
    {
        return $this->entity->getNumber();
    }

    /**
     * Sets the number of the product.
     *
     * @param string $number The number of the product
     */
    public function setNumber($number)
    {
        $this->entity->setNumber($number);
    }

    /**
     * Returns the globalTradeItemNumber of the product.
     *
     * @VirtualProperty
     * @SerializedName("globalTradeItemNumber")
     * @Groups({"cart"})
     *
     * @return string The globalTradeItemNumber of the product
     */
    public function getGlobalTradeItemNumber()
    {
        return $this->entity->getGlobalTradeItemNumber();
    }

    /**
     * Sets the globalTradeItemNumber of the product.
     *
     * @param string $globalTradeItemNumber The globalTradeItemNumber of the product
     */
    public function setGlobalTradeItemNumber($globalTradeItemNumber)
    {
        $this->entity->setGlobalTradeItemNumber($globalTradeItemNumber);
    }

    /**
     * Returns the internalItemNumber of the product.
     *
     * @VirtualProperty
     * @SerializedName("internalItemNumber")
     *
     * @return string The internalItemNumber of the product
     */
    public function getInternalItemNumber()
    {
        return $this->entity->getInternalItemNumber();
    }

    /**
     * Sets the internalItemNumber of the product.
     *
     * @param string $internalItemNumber The number of the product
     */
    public function setInternalItemNumber($internalItemNumber)
    {
        $this->entity->setInternalItemNumber($internalItemNumber);
    }

    /**
     * Sets the supplier of the product.
     *
     * @param AccountInterface $supplier
     */
    public function setSupplier($supplier)
    {
        $this->entity->setSupplier($supplier);
    }

    /**
     * @VirtualProperty
     * @SerializedName("isRecurringPrice")
     *
     * @return bool
     */
    public function isRecurringPrice()
    {
        return $this->entity->isRecurringPrice();
    }

    /**
     * @param bool $isRecurringPrice
     *
     * @return self
     */
    public function setIsRecurringPrice($isRecurringPrice)
    {
        $this->entity->setIsRecurringPrice($isRecurringPrice);

        return $this;
    }

    /**
     * Returns the supplier of the product.
     *
     * @VirtualProperty
     * @SerializedName("supplier")
     *
     * @return object The supplier of the product
     */
    public function getSupplier()
    {
        $values = null;
        $supplier = $this->entity->getSupplier();
        if ($supplier !== null) {
            // Returns no api entity because it will cause a nesting level exception
            $values = [
                'id' => $supplier->getId(),
                'name' => $supplier->getName(),
            ];
        }

        return $values;
    }

    /**
     * Returns the cost of the product.
     *
     * @VirtualProperty
     * @SerializedName("cost")
     * @Groups({"cart"})
     *
     * @return float The cost of the product
     */
    public function getCost()
    {
        return $this->entity->getCost();
    }

    /**
     * Sets the cost of the product.
     *
     * @param float $cost The cost of the product
     */
    public function setCost($cost)
    {
        $this->entity->setCost($cost);
    }

    /**
     * Sets the priceinfo of the product.
     *
     * @param string $priceInfo The cost of the product
     */
    public function setPriceInfo($priceInfo)
    {
        $this->entity->setPriceInfo($priceInfo);
    }

    /**
     * Returns the cost of the product.
     *
     * @VirtualProperty
     * @SerializedName("priceInfo")
     *
     * @return float The cost of the product
     */
    public function getPriceInfo()
    {
        return $this->entity->getPriceInfo();
    }

    /**
     * Returns the manufacturer of the product.
     *
     * @VirtualProperty
     * @SerializedName("manufacturer")
     *
     * @return string The manufacturer of the product
     */
    public function getManufacturer()
    {
        return $this->entity->getManufacturer();
    }

    /**
     * Sets the manufacturer of the product.
     *
     * @param string $manufacturer The manufacturer of the product
     */
    public function setManufacturer($manufacturer)
    {
        $this->entity->setManufacturer($manufacturer);
    }

    /**
     * Returns the parent of the product.
     *
     * @VirtualProperty
     * @SerializedName("parent")
     *
     * @return ProductInterface The parent of the product
     */
    public function getParent()
    {
        $parent = $this->entity->getParent();

        if ($parent) {
            return new static(
                $parent,
                $this->locale,
                $this->priceFormatter,
                $this->productLocaleManager,
                $this->accountManager);
        }

        return null;
    }

    /**
     * Sets the parent of the product.
     *
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
     * Returns the children of the product.
     *
     * @return ProductInterface[]
     */
    public function getChildren()
    {
        return $this->entity->getChildren();
    }

    /**
     * Adds a product attribute to the product.
     *
     * @param ProductAttributeEntity $productAttribute
     */
    public function addProductAttribute(ProductAttributeEntity $productAttribute)
    {
        $this->entity->addProductAttribute($productAttribute);
    }

    /**
     * Returns the type of the product.
     *
     * @VirtualProperty
     * @SerializedName("type")
     *
     * @return Type|null The type of the product
     */
    public function getType()
    {
        if ($this->entity->getType()) {
            return new Type($this->entity->getType(), $this->locale);
        }

        return null;
    }

    /**
     * Sets the type of the product.
     *
     * @param TypeEntity $type The type of the product
     */
    public function setType(TypeEntity $type)
    {
        $this->entity->setType($type);
    }

    /**
     * Returns the status of the product.
     *
     * @VirtualProperty
     * @SerializedName("status")
     *
     * @return Status The status of the product
     */
    public function getStatus()
    {
        return new Status($this->entity->getStatus(), $this->locale);
    }

    /**
     * Sets the delivery status of the product.
     *
     * @param DeliveryStatusEntity $deliveryStatus The delivery status of the product
     */
    public function setDeliveryStatus(DeliveryStatusEntity $deliveryStatus)
    {
        $this->entity->setDeliveryStatus($deliveryStatus);
    }

    /**
     * Returns the delivery status of the product.
     *
     * @VirtualProperty
     * @SerializedName("deliveryStatus")
     *
     * @return DeliveryStatus The delivery status of the product
     */
    public function getDeliveryStatus()
    {
        $status = $this->entity->getDeliveryStatus();
        if ($status !== null) {
            return new DeliveryStatus($status, $this->locale);
        }

        return null;
    }

    /**
     * Sets the status of the product.
     *
     * @param StatusEntity $status The status of the product
     */
    public function setStatus(StatusEntity $status)
    {
        $this->entity->setStatus($status);
    }

    /**
     * Returns the orderUnit of the product.
     *
     * @VirtualProperty
     * @SerializedName("orderUnit")
     * @Groups({"cart"})
     *
     * @return Unit
     */
    public function getOrderUnit()
    {
        $unit = $this->entity->getOrderUnit();
        if (!is_null($unit)) {
            return new Unit($unit, $this->locale);
        }

        return null;
    }

    /**
     * Sets the order unit of the product.
     *
     * @param UnitEntity $unit
     */
    public function setOrderUnit(UnitEntity $unit = null)
    {
        $this->entity->setOrderUnit($unit);
    }

    /**
     * Returns the contentUnit of the product.
     *
     * @VirtualProperty
     * @SerializedName("contentUnit")
     * @Groups({"cart"})
     *
     * @return Unit
     */
    public function getContentUnit()
    {
        $unit = $this->entity->getContentUnit();
        if (!is_null($unit)) {
            return new Unit($unit, $this->locale);
        }

        return null;
    }

    /**
     * Sets the order contentUnit of the product.
     *
     * @param UnitEntity $unit
     */
    public function setContentUnit(UnitEntity $unit = null)
    {
        $this->entity->setContentUnit($unit);
    }

    /**
     * Returns the tax class of the product.
     *
     * @VirtualProperty
     * @SerializedName("taxClass")
     *
     * @return TaxClass The status of the product
     */
    public function getTaxClass()
    {
        $taxClass = $this->entity->getTaxClass();
        if ($taxClass) {
            return new TaxClass($this->entity->getTaxClass(), $this->locale);
        }

        return null;
    }

    /**
     * Sets the tax class of the product.
     *
     * @param TaxClassEntity $taxClass The tax class of the product
     */
    public function setTaxClass(TaxClassEntity $taxClass)
    {
        $this->entity->setTaxClass($taxClass);
    }

    /**
     * Returns the attribute set of the product.
     *
     * @VirtualProperty
     * @SerializedName("attributeSet")
     *
     * @return AttributeSet The attribute set of the product
     */
    public function getAttributeSet()
    {
        $attributeSet = $this->entity->getAttributeSet();
        if ($attributeSet) {
            return new AttributeSet($attributeSet, $this->locale);
        }

        return null;
    }

    /**
     * Sets the attribute set of the product.
     *
     * @param AttributeSetEntity $attributeSet The attribute set of the product
     */
    public function setAttributeSet(AttributeSetEntity $attributeSet)
    {
        $this->entity->setAttributeSet($attributeSet);
    }

    /**
     * Removes the given price from the product.
     *
     * @param ProductPriceEntity $price
     */
    public function removePrice(ProductPriceEntity $price)
    {
        $this->entity->removePrice($price);
    }

    /**
     * Returns the prices for the product.
     *
     * @VirtualProperty
     * @SerializedName("prices")
     *
     * @return ProductPrice[]
     */
    public function getPrices()
    {
        $priceEntities = $this->entity->getPrices();

        $prices = [];
        foreach ($priceEntities as $priceEntity) {
            $prices[] = new ProductPrice($priceEntity, $this->locale);
        }

        return $prices;
    }

    /**
     * Returns the scale price for a certain currency.
     *
     * @param string $currency
     *
     * @return ProductPrice[]
     */
    public function getScalePriceForCurrency($currency = 'EUR')
    {
        $scalePrice = null;
        $prices = $this->entity->getPrices();
        if ($prices) {
            foreach ($prices as $price) {
                if ($price->getCurrency()->getCode() == $currency) {
                    $scalePrice[] = $price;
                }
            }
        }

        return $scalePrice;
    }

    /**
     * Returns the formatted special price for the product by a given currency and locale.
     *
     * @param string $currency
     * @param null|string $formatterLocale
     *
     * @return string
     */
    public function getFormattedSpecialPriceForCurrency($currency = 'EUR', $formatterLocale = null)
    {
        $price = $this->getSpecialPriceForCurrency($currency);
        if ($price) {
            return $this->getFormattedPrice($price->getPrice(), $currency, $formatterLocale);
        }

        return '';
    }

    /**
     * Returns the special price for a certain currency.
     *
     * @param string $currency
     *
     * @return ProductPrice[]
     */
    public function getSpecialPriceForCurrency($currency = 'EUR')
    {
        $specialPrices = $this->entity->getSpecialPrices();
        if ($specialPrices) {
            foreach ($specialPrices as $specialPriceEntity) {
                if ($specialPriceEntity->getCurrency()->getCode() == $currency) {
                    $startDate = $specialPriceEntity->getStartDate();
                    $endDate = $specialPriceEntity->getEndDate();
                    $now = new \DateTime();
                    if (($now >= $startDate && $now <= $endDate) ||
                        ($now >= $startDate && empty($endDate)) ||
                        (empty($startDate) && empty($endDate))
                    ) {
                        return $specialPriceEntity;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Returns the bulk price for a certain quantity of the product by a given currency.
     *
     * @param float $quantity
     * @param string $currency
     *
     * @return ProductPrice[]
     */
    public function getBulkPriceForCurrency($quantity, $currency = 'EUR')
    {
        $bulkPrice = null;
        $prices = $this->entity->getPrices();
        if ($prices) {
            $bestDifference = PHP_INT_MAX;
            foreach ($prices as $price) {
                if ($price->getCurrency()->getCode() == $currency &&
                    $price->getMinimumQuantity() <= $quantity &&
                    ($quantity - $price->getMinimumQuantity()) < $bestDifference
                ) {
                    $bestDifference = $quantity - $price->getMinimumQuantity();
                    $bulkPrice = $price;
                }
            }
        }

        return $bulkPrice;
    }

    /**
     * Returns the formatted base price for the product by a given currency and locale.
     *
     * @param string $currency
     *
     * @return string
     */
    public function getFormattedBasePriceForCurrency($currency = 'EUR')
    {
        $price = $this->getBasePriceForCurrency($currency);
        if ($price) {
            return $this->getFormattedPrice($price->getPrice(), $currency, $this->locale);
        }

        return '';
    }

    /**
     * Returns the base price for the product by a given currency.
     *
     * @param string $currency
     *
     * @return ProductPrice
     */
    public function getBasePriceForCurrency($currency = 'EUR')
    {
        $prices = $this->entity->getPrices();
        if ($prices) {
            foreach ($prices as $price) {
                if ($price->getCurrency()->getCode() == $currency && $price->getMinimumQuantity() == 0) {
                    return new ProductPrice($price, $this->locale);
                }
            }
        }

        return null;
    }

    /**
     * Helper function to get a formatted price for a given currency and locale.
     *
     * @param int $price
     * @param string $symbol
     * @param string $locale
     * @Groups({"cart"})
     *
     * @return string price
     */
    public function getFormattedPrice($price, $symbol = 'EUR', $locale = 'de')
    {
        return $this->priceFormatter->format(
            (float) $price,
            null,
            $locale,
            $symbol,
            PriceFormatter::CURRENCY_LOCATION_SUFFIX
        );
    }

    /**
     * Returns the attributes for the product.
     *
     * @VirtualProperty
     * @SerializedName("attributes")
     *
     * @return ProductAttributes[]
     */
    public function getAttributes()
    {
        $attributeEntities = $this->entity->getProductAttributes();

        $attributes = [];
        foreach ($attributeEntities as $attributesEntity) {
            $attributes[] = new ProductAttribute(
                $attributesEntity,
                $this->locale,
                $this->productLocaleManager->getFallbackLocale()
            );
        }

        return $attributes;
    }

    /**
     * Adds a category to the product.
     *
     * @param CategoryEntity $category
     */
    public function addCategory(CategoryEntity $category)
    {
        $this->entity->addCategory($category);
    }

    /**
     * Removes a category from the product.
     *
     * @param CategoryEntity $category
     */
    public function removeCategory(CategoryEntity $category)
    {
        $this->entity->removeCategory($category);
    }

    /**
     * Returns the categories for the product.
     *
     * @VirtualProperty
     * @SerializedName("categories")
     *
     * @return CategoryEntity[]
     */
    public function getCategories()
    {
        $categoryEntities = $this->entity->getCategories();

        $categories = [];
        if ($categoryEntities) {
            foreach ($categoryEntities as $categoryEntity) {
                $categories[] = new Category($categoryEntity, $this->locale);
            }
        }

        return $categories;
    }

    /**
     * Sets the changer of the product.
     *
     * @param UserInterface $user The changer of the product
     */
    public function setChanger(UserInterface $user)
    {
        $this->entity->setChanger($user);
    }

    /**
     * Sets the creator of the product.
     *
     * @param UserInterface $user The creator of the product
     */
    public function setCreator(UserInterface $user)
    {
        $this->entity->setCreator($user);
    }

    /**
     * Returns the creator of the product.
     *
     * @return creator/owner of the product
     */
    public function getCreator()
    {
        return $this->entity->getCreator();
    }

    /**
     * Sets the change time of the product.
     *
     * @param \DateTime $changed
     */
    public function setChanged(\DateTime $changed)
    {
        $this->entity->setChanged($changed);
    }

    /**
     * Sets the created time of the product.
     *
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created)
    {
        $this->entity->setCreated($created);
    }

    /**
     * Adds a media to the product.
     *
     * @param Media $media
     */
    public function addMedia(Media $media)
    {
        $this->entity->addMedia($media->getEntity());
    }

    /**
     * Removes a media from the product.
     *
     * @param Media $media
     */
    public function removeMedia(Media $media)
    {
        $this->entity->removeMedia($media->getEntity());
    }

    /**
     * @param $media
     */
    public function setMedia($media)
    {
        $this->media = $media;
    }

    /**
     * Returns the media for the product.
     *
     * @VirtualProperty
     * @SerializedName("media")
     * @Groups({"cart"})
     *
     * @return Media[]
     */
    public function getMedia()
    {
        // if media was set by setMedia() use this->media
        if ($this->media) {
            return $this->media;
        }
        $mediaCollection = [];
        $media = $this->entity->getMedia();
        if (!$media) {
            return $mediaCollection;
        }
        foreach ($media as $medium) {
            $mediaCollection[] = new Media($medium, $this->locale);
        }

        return $mediaCollection;
    }

    /**
     * Returns the images for the product.
     *
     * @VirtualProperty
     * @SerializedName("images")
     * @Groups({"cart"})
     *
     * @return Media[]
     */
    public function getImages()
    {
        return $this->getMediaByType(Media::MEDIA_TYPE_IMAGE);
    }

    /**
     * Returns the videos for the product.
     *
     * @VirtualProperty
     * @SerializedName("videos")
     * @Groups({"cart"})
     *
     * @return Media[]
     */
    public function getVideos()
    {
        return $this->getMediaByType(Media::MEDIA_TYPE_VIDEO);
    }

    /**
     * Returns the audios for the product.
     *
     * @VirtualProperty
     * @SerializedName("audios")
     * @Groups({"cart"})
     *
     * @return Media[]
     */
    public function getAudios()
    {
        return $this->getMediaByType(Media::MEDIA_TYPE_AUDIO);
    }

    /**
     * Returns the documents for the product.
     *
     * @VirtualProperty
     * @SerializedName("documents")
     * @Groups({"cart"})
     *
     * @return Media[]
     */
    public function getDocuments()
    {
        return $this->getMediaByType(Media::MEDIA_TYPE_DOCUMENT);
    }

    /**
     * Returns true when collection of media contains media with specific id.
     *
     * @param Media $media
     *
     * @return bool
     */
    public function containsMedia(Media $media)
    {
        return $this->entity->getMedia()->contains($media->getEntity());
    }

    /**
     * Returns a bool indicating if all prices of the product are gross prices.
     *
     * @VirtualProperty
     * @SerializedName("areGrossPrices")
     *
     * @return bool
     */
    public function getAreGrossPrices()
    {
        return $this->entity->getAreGrossPrices();
    }

    /**
     * Sets the are gross prices flag on a product.
     *
     * @param bool $areGrossPrices
     */
    public function setAreGrossPrices($areGrossPrices = false)
    {
        $this->entity->setAreGrossPrices($areGrossPrices);
    }

    /**
     * Returns a media array by a given type for the product.
     *
     * @param string
     *
     * @return array
     */
    private function getMediaByType($type)
    {
        $collection = [];
        $media = $this->getMedia();
        if (!$media) {
            return $collection;
        }
        foreach ($media as $asset) {
            if ($asset->isTypeOf($type)) {
                $collection[] = $asset;
            }
        }

        return $collection;
    }

    /**
     * @return ProductTranslation
     */
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
     * Returns the special prices for the product.
     *
     * @VirtualProperty
     * @SerializedName("specialPrices")
     *
     * @return \Sulu\Bundle\ProductBundle\Api\SpecialPrice[]
     */
    public function getSpecialPrices()
    {
        $specialPrices = $this->entity->getSpecialPrices();

        $specialPricesList = [];
        foreach ($specialPrices as $specialPrice) {
            $specialPricesList[] = new SpecialPrice($specialPrice, $this->locale);
        }

        return $specialPricesList;
    }

    /**
     * Adds a special price to the product.
     *
     * @param SpecialPriceEntity $specialPrice
     */
    public function addSpecialPrice(SpecialPriceEntity $specialPrice)
    {
        $this->entity->addSpecialPrice($specialPrice);
    }

    /**
     * Removes a special price from the product.
     *
     * @param SpecialPriceEntity $specialPrice
     */
    public function removeSpecialPrice(SpecialPriceEntity $specialPrice)
    {
        $this->entity->removeSpecialPrice($specialPrice);
    }

    /**
     * @param string $searchTerms
     */
    public function setSearchTerms($searchTerms)
    {
        $this->entity->setSearchTerms($searchTerms);
    }

    /**
     * @VirtualProperty
     * @SerializedName("searchTerms")
     *
     * @return string
     */
    public function getSearchTerms()
    {
        return $this->entity->getSearchTerms();
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
        return $this->entity->isValidShopProduct($defaultCurrency);
    }

    /**
     * @VirtualProperty
     * @SerializedName("tags")
     *
     * @return string[]
     */
    public function getTags()
    {
        return $this->entity->getTagNameArray();
    }

    /**
     * @VirtualProperty
     * @SerializedName("addons")
     *
     * @return ApiProductInterface[]
     */
    public function getAddons()
    {
        $apiAddons = [];
        $addons = $this->entity->getAddons();
        /** @var AddonEntity $addon */
        foreach ($addons as $addon) {
            $apiAddons[] = new static(
                $addon->getAddon(),
                $this->locale,
                $this->priceFormatter,
                $this->productLocaleManager
            );
        }

        return $apiAddons;
    }

    /**
     * @VirtualProperty
     * @SerializedName("numberOfVariants")
     *
     * @return int
     */
    public function getNumberOfVariants()
    {
        return $this->entity->getNumberOfVariants();
    }
}
