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
 * SpecialPrice.
 */
class SpecialPrice
{
    /**
     * @var float
     */
    private $price;

    /**
     * @var \DateTime
     */
    private $startDate;

    /**
     * @var \DateTime
     */
    private $endDate;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    private $currency;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    private $product;

    /**
     * Set price.
     *
     * @param float $price
     *
     * @return SpecialPrice
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price.
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set start date.
     *
     * @param \DateTime $startDate
     *
     * @return SpecialPrice
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Get start date.
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set end date.
     *
     * @param \DateTime $endDate
     *
     * @return SpecialPrice
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Get end date.
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
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
     * Set currency.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Currency $currency
     *
     * @return SpecialPrice
     */
    public function setCurrency(\Sulu\Bundle\ProductBundle\Entity\Currency $currency = null)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set product.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     *
     * @return SpecialPrice
     */
    public function setProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }
}
