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

use JMS\Serializer\Annotation\Exclude;

class AddonPrice
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var Currency
     */
    protected $currency;

    /**
     * @var float
     */
    protected $price;

    /**
     * @Exclude()
     * @var Addon
     */
    protected $addon;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param Currency $currency
     *
     * @return self
     */
    public function setCurrency(Currency $currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param float $price
     *
     * @return self
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Addon
     */
    public function getAddon()
    {
        return $this->addon;
    }

    /**
     * @param Addon $addon
     *
     * @return self
     */
    public function setAddon(Addon $addon)
    {
        $this->addon = $addon;

        return $this;
    }
}
