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

/**
 * Addon
 */
class Addon
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var ProductInterface
     */
    private $addon;

    /**
     * @var ArrayCollection
     */
    private $addonPrices;

    public function __construct()
    {
        $this->addonPrices = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set product
     *
     * @param ProductInterface $product
     *
     * @return self
     */
    public function setProduct(ProductInterface $product)
    {
        $this->product = $product;
    
        return $this;
    }

    /**
     * Get product
     *
     * @return ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set addon
     *
     * @param ProductInterface $addon
     *
     * @return self
     */
    public function setAddon(ProductInterface $addon)
    {
        $this->addon = $addon;
    
        return $this;
    }

    /**
     * Get addon
     *
     * @return ProductInterface
     */
    public function getAddon()
    {
        return $this->addon;
    }

    /**
     * @return ArrayCollection
     */
    public function getAddonPrices()
    {
        return $this->addonPrices;
    }

    /**
     * @param AddonPrice $addonPrice
     *
     * @return self
     */
    public function addAddonPrice(AddonPrice $addonPrice)
    {
        $this->addonPrices->add($addonPrice);

        return $this;
    }

    /**
     * @param AddonPrice $addonPrice
     *
     * @return self
     */
    public function removeAddonPrice(AddonPrice $addonPrice)
    {
        $this->addonPrices->removeElement($addonPrice);

        return $this;
    }
}
