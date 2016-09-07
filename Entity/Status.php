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
 * Status.
 */
class Status
{
    const IMPORTED = 1;
    const SUBMITTED = 2;
    const ACTIVE = 3;
    const REJECTED = 4;
    const INACTIVE = 5;
    const CHANGED = 6;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $products;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $translations;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set id.
     *
     * @return int
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Add products.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $products
     *
     * @return Status
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
     * Add translations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\StatusTranslation $translations
     *
     * @return Status
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\StatusTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * Remove translations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\StatusTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\StatusTranslation $translations)
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
     * Returns the translation for the given locale.
     *
     * @param string $locale
     *
     * @return StatusTranslation
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
}
