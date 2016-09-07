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
 * ProductPrice.
 */
class ProductPrice
{
    /**
     * @var float
     */
    private $minimumQuantity = 0;

    /**
     * @var float
     */
    private $price;

    /**
     * @var int
     */
    private $id;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    private $currency;

    /**
     * Set minimumQuantity.
     *
     * @param float $minimumQuantity
     *
     * @return ProductPrice
     */
    public function setMinimumQuantity($minimumQuantity)
    {
        $this->minimumQuantity = $minimumQuantity;

        return $this;
    }

    /**
     * Get minimumQuantity.
     *
     * @return float
     */
    public function getMinimumQuantity()
    {
        return $this->minimumQuantity;
    }

    /**
     * Set price.
     *
     * @param float $price
     *
     * @return ProductPrice
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
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set product.
     *
     * @param ProductInterface $product
     *
     * @return ProductPrice
     */
    public function setProduct(ProductInterface $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product.
     *
     * @return ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set currency.
     *
     * @param Currency $currency
     *
     * @return ProductPrice
     */
    public function setCurrency(Currency $currency = null)
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
}
