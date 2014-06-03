<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\SecurityBundle\Entity\User;

/**
 * Defines the interface for a product
 * @package Sulu\Bundle\Product\BaseBundle\Entity
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
     * Set code
     *
     * @param string $code
     * @return Product
     */
    public function setCode($code);

    /**
     * Get code
     *
     * @return string
     */
    public function getCode();

    /**
     * Set number
     *
     * @param string $number
     * @return Product
     */
    public function setNumber($number);

    /**
     * Get number
     *
     * @return string
     */
    public function getNumber();

    /**
     * Set manufacturer
     *
     * @param string $manufacturer
     * @return Product
     */
    public function setManufacturer($manufacturer);

    /**
     * Get manufacturer
     *
     * @return string
     */
    public function getManufacturer();

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Product
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
     * @return Product
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
     * @return Product
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
     * @return Product
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
     * @param Template $template
     * @return Product
     */
    public function setTemplate(Template $template);

    /**
     * Get template
     *
     * @return Template
     */
    public function getTemplate();

    /**
     * Set status
     *
     * @param Status $status
     * @return Product
     */
    public function setStatus(Status $status = null);

    /**
     * Get status
     *
     * @return Status
     */
    public function getStatus();

    /**
     * Add sets
     *
     * @param Set $sets
     * @return Product
     */
    public function addSet(Set $sets);

    /**
     * Remove sets
     *
     * @param Set $sets
     */
    public function removeSet(Set $sets);

    /**
     * Get sets
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSets();

    /**
     * Add relations
     *
     * @param ProductInterface $relations
     * @return Product
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
     * @return Product
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
     * @param User $changer
     * @return ProductInterface
     */
    public function setChanger(User $changer = null);

    /**
     * Get changer
     *
     * @return User
     */
    public function getChanger();

    /**
     * Set creator
     *
     * @param User $creator
     * @return ProductInterface
     */
    public function setCreator(User $creator = null);

    /**
     * Get creator
     *
     * @return User
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
     * Add extras
     *
     * @param Extra $extras
     * @return ProductInterface
     */
    public function addExtra(Extra $extras);

    /**
     * Remove extras
     *
     * @param Extra $extras
     */
    public function removeExtra(Extra $extras);

    /**
     * Get extras
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getExtras();
}
