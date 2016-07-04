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

use Doctrine\Common\Collections\ArrayCollection;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Component\Security\Authentication\UserInterface;

/**
 * Defines the interface for a product
 * @package Sulu\Bundle\ProductBundle\Entity
 */
interface ProductInterface
{
    /**
     * Get id
     *
     * @return integer
     */
    public function getId();

    /**
     * Set number
     *
     * @param string $number
     * @return BaseProduct
     */
    public function setNumber($number);

    /**
     * Get number
     *
     * @return string
     */
    public function getNumber();

    /**
     * Set globalTradeItemNumber
     *
     * @param string $globalTradeItemNumber
     * @return BaseProduct
     */
    public function setGlobalTradeItemNumber($globalTradeItemNumber);

    /**
     * Get globalTradeItemNumber
     *
     * @return string
     */
    public function getGlobalTradeItemNumber();

    /**
     * Set manufacturer
     *
     * @param string $manufacturer
     * @return BaseProduct
     */
    public function setManufacturer($manufacturer);

    /**
     * Get manufacturer
     *
     * @return string
     */
    public function getManufacturer();

    /**
     * Set cost
     *
     * @param double $cost
     * @return ProductInterface
     */
    public function setCost($cost);

    /**
     * Get cost
     *
     * @return double
     */
    public function getCost();

    /**
     * Set priceInfo
     *
     * @param string $priceInfo
     * @return BaseProduct
     */
    public function setPriceInfo($priceInfo);

    /**
     * Get priceInfo
     *
     * @return string
     */
    public function getPriceInfo();

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return BaseProduct
     */
    public function setCreated($created);

    /**
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated();

    /**
     * Set changed
     *
     * @param \DateTime $changed
     * @return BaseProduct
     */
    public function setChanged($changed);

    /**
     * Get changed
     *
     * @return \DateTime
     */
    public function getChanged();

    /**
     * Set manufacturerCountry
     *
     * @param Country $manufacturerCountry
     * @return BaseProduct
     */
    public function setManufacturerCountry(Country $manufacturerCountry = null);

    /**
     * Get manufacturerCountry
     *
     * @return Country
     */
    public function getManufacturerCountry();

    /**
     * Set type
     *
     * @param Type $type
     * @return BaseProduct
     */
    public function setType(Type $type);

    /**
     * Get type
     *
     * @return Type
     */
    public function getType();

    /**
     * Set template
     *
     * @param AttributeSet $template
     * @return BaseProduct
     */
    public function setAttributeSet(AttributeSet $template);

    /**
     * Get template
     *
     * @return AttributeSet
     */
    public function getAttributeSet();

    /**
     * Set status
     *
     * @param Status $status
     * @return BaseProduct
     */
    public function setStatus(Status $status = null);

    /**
     * Get status
     *
     * @return Status
     */
    public function getStatus();

    /**
     * Get supplier
     *
     * @return AccountInterface Supplier
     */
    public function getSupplier();

    /**
     * Set taxClass
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass
     * @return BaseProduct
     */
    public function setTaxClass(\Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass = null);

    /**
     * Get taxClass
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    public function getTaxClass();

    /**
     * Add relations
     *
     * @param ProductInterface $relations
     * @return BaseProduct
     */
    public function addRelation(ProductInterface $relations);

    /**
     * Remove relations
     *
     * @param ProductInterface $relations
     */
    public function removeRelation(ProductInterface $relations);

    /**
     * Get relations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRelations();

    /**
     * Add upsells
     *
     * @param ProductInterface $upsells
     * @return BaseProduct
     */
    public function addUpsell(ProductInterface $upsells);

    /**
     * Remove upsells
     *
     * @param ProductInterface $upsells
     */
    public function removeUpsell(ProductInterface $upsells);

    /**
     * Get upsells
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUpsells();

    /**
     * Add crosssells
     *
     * @param ProductInterface $crosssells
     * @return ProductInterface
     */
    public function addCrosssell(ProductInterface $crosssells);

    /**
     * Remove crosssells
     *
     * @param ProductInterface $crosssells
     */
    public function removeCrosssell(ProductInterface $crosssells);

    /**
     * Get crosssells
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCrosssells();

    /**
     * Set changer
     *
     * @param \Sulu\Component\Security\Authentication\UserInterface $changer
     * @return ProductInterface
     */
    public function setChanger(UserInterface $changer = null);

    /**
     * Get changer
     *
     * @return UserInterface
     */
    public function getChanger();

    /**
     * Set creator
     *
     * @param \Sulu\Component\Security\Authentication\UserInterface $creator
     * @return ProductInterface
     */
    public function setCreator(UserInterface $creator = null);

    /**
     * Get creator
     *
     * @return UserInterface
     */
    public function getCreator();

    /**
     * Set parent
     *
     * @param ProductInterface $parent
     * @return ProductInterface
     */
    public function setParent(ProductInterface $parent = null);

    /**
     * Get parent
     *
     * @return ProductInterface
     */
    public function getParent();

    /**
     * Add children
     *
     * @param ProductInterface $children
     * @return ProductInterface
     */
    public function addChildren(ProductInterface $children);

    /**
     * Remove children
     *
     * @param ProductInterface $children
     */
    public function removeChildren(ProductInterface $children);

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getChildren();

    /**
     * Add prices
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices
     * @return Product
     */
    public function addPrice(\Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices);

    /**
     * Remove prices
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices
     */
    public function removePrice(\Sulu\Bundle\ProductBundle\Entity\ProductPrice $prices);

    /**
     * Get prices
     *
     * @return ProductPrice[]
     */
    public function getPrices();

    /**
     * Add attributes
     *
     * @param ProductAttribute $productAttributes
     * @return ProductInterface
     */
    public function addProductAttribute(ProductAttribute $productAttributes);

    /**
     * Remove attributes
     *
     * @param ProductAttribute $productAttributes
     */
    public function removeProductAttribute(ProductAttribute $productAttributes);

    /**
     * Get attributes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProductAttributes();

    /**
     * Add translations
     *
     * @param ProductTranslation $translations
     * @return ProductInterface
     */
    public function addTranslation(ProductTranslation $translations);

    /**
     * Remove translations
     *
     * @param ProductTranslation $translations
     */
    public function removeTranslation(ProductTranslation $translations);

    /**
     * Get translations
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTranslations();

    /**
     * Get one specific translation
     * @param string $locale The locale of the translation to get
     * @return ProductTranslation
     */
    public function getTranslation($locale);

    /**
     * Add extras
     *
     * @param Addon $addon
     * @return ProductInterface
     */
    public function addAddon(Addon $addon);

    /**
     * Remove extras
     *
     * @param Addon $addon
     */
    public function removeAddon(Addon $addon);

    /**
     * Get extras
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAddons();

    /**
     * Add setProducts
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts
     * @return BaseProduct
     */
    public function addSetProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts);

    /**
     * Remove setProducts
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts
     */
    public function removeSetProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $setProducts);

    /**
     * Get setProducts
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSetProducts();

    /**
     * Add categories
     *
     * @param \Sulu\Bundle\CategoryBundle\Entity\Category $categories
     * @return BaseProduct
     */
    public function addCategory(\Sulu\Bundle\CategoryBundle\Entity\Category $categories);

    /**
     * Remove categories
     *
     * @param \Sulu\Bundle\CategoryBundle\Entity\Category $categories
     */
    public function removeCategory(\Sulu\Bundle\CategoryBundle\Entity\Category $categories);

    /**
     * Get categories
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCategories();

    /**
     * Get media
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getMedia();

    /**
     * Get media
     * @param Media $media
     */
    public function addMedia(Media $media);

    /**
     * Remove media
     * @param Media $media
     */
    public function removeMedia(Media $media);

    /**
     * Are all prices of this products gross prices
     * @return boolean
     */
    public function getAreGrossPrices();

    /**
     * Sets prices of this products gross prices
     * @param $areGrossPrices
     */
    public function setAreGrossPrices($areGrossPrices);

    /**
     * @param string $searchTerms
     */
    public function setSearchTerms($searchTerms);

    /**
     * @return string
     */
    public function getSearchTerms();

    /**
     * Helper method to check if the product is
     * a valid shop product.
     *
     * @param string $defaultCurrency
     *
     * @return bool
     */
    public function isValidShopProduct($defaultCurrency);

    /**
     * @return ArrayCollection
     */
    public function getTags();

    /**
     * @param Tag $tag
     *
     * @return $this
     */
    public function addTag(Tag $tag);

    /**
     * @param Tag $tag
     */
    public function removeTag(Tag $tag);

    /**
     * @return string[]
     */
    public function getTagNameArray();

    /**
     * @return SpecialPrice[]
     */
    public function getSpecialPrices();
}
