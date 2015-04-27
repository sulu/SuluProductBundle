<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SpecialPrice
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
    private $start;

    /**
     * @var \DateTime
     */
    private $end;

    /**
     * @var integer
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
     * Set price
     *
     * @param float $price
     * @return SpecialPrice
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set start
     *
     * @param \DateTime $start
     * @return SpecialPrice
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end
     *
     * @param \DateTime $end
     * @return SpecialPrice
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set currency
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Currency $currency
     * @return SpecialPrice
     */
    public function setCurrency(\Sulu\Bundle\ProductBundle\Entity\Currency $currency = null)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set product
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     * @return SpecialPrice
     */
    public function setProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $product = null)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }
}
