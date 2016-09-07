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
 * DeliveryStatus.
 */
class DeliveryStatus
{
    const AVAILABLE = 1;
    const NOT_AVAILABLE = 2;

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
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @param $id
    */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Add translations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation $translations
     *
     * @return DeliveryStatus
     */
    public function addTranslation(\Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * Remove translations.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation $translations
     */
    public function removeTranslation(\Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation $translations)
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
     * Add products.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Product $products
     *
     * @return DeliveryStatus
     */
    public function addProduct(\Sulu\Bundle\ProductBundle\Entity\Product $products)
    {
        $this->products[] = $products;

        return $this;
    }

    /**
     * Remove products.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Product $products
     */
    public function removeProduct(\Sulu\Bundle\ProductBundle\Entity\Product $products)
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
}
