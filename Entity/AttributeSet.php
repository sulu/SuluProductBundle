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

/**
 * Template.
 */
class AttributeSet
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $translations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $products;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $attributes;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();
        $this->attributes = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add translations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation $translations
     *
     * @return AttributeSet
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * Remove translations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation $translations)
    {
        $this->translations->removeElement($translations);
    }

    /**
     * Get translations.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * Returns the translation with the given locale.
     *
     * @param string $locale The locale to return
     *
     * @return AttributeSetTranslation
     */
    public function getTranslation($locale)
    {
        $translation = null;
        foreach ($this->translations as $translationData) {
            if ($translationData->getLocale() == $locale) {
                $translation = $translationData;
                break;
            }
        }

        return $translation;
    }

    /**
     * Add products.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $products
     *
     * @return AttributeSet
     */
    public function addProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $products)
    {
        $this->products[] = $products;

        return $this;
    }

    /**
     * Remove products.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $products
     */
    public function removeProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $products)
    {
        $this->products->removeElement($products);
    }

    /**
     * Get products.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * Add attributes.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Attribute $attributes
     *
     * @return AttributeSet
     */
    public function addAttribute(\Sulu\Bundle\ProductBundle\Entity\Attribute $attributes)
    {
        $this->attributes[] = $attributes;

        return $this;
    }

    /**
     * Remove attributes.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Attribute $attributes
     */
    public function removeAttribute(\Sulu\Bundle\ProductBundle\Entity\Attribute $attributes)
    {
        $this->attributes->removeElement($attributes);
    }

    /**
     * Get attributes.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
}
