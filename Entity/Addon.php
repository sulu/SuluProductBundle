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

use Doctrine\ORM\Mapping as ORM;

/**
 * Extra
 */
class Addon
{
    /**
     * @var string
     */
    private $price;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Product
     */
    private $product;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Product
     */
    private $extra;

    /**
     * Set price
     *
     * @param string $price
     * @return Addon
     */
    public function setPrice($price)
    {
        $this->price = $price;
    
        return $this;
    }

    /**
     * Get price
     *
     * @return string 
     */
    public function getPrice()
    {
        return $this->price;
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
     * Set product
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $product
     * @return Addon
     */
    public function setProduct(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $product)
    {
        $this->product = $product;
    
        return $this;
    }

    /**
     * Get product
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set extra
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $extra
     * @return Addon
     */
    public function setExtra(\Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface $extra)
    {
        $this->extra = $extra;
    
        return $this;
    }

    /**
     * Get extra
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\ProductInterface
     */
    public function getExtra()
    {
        return $this->extra;
    }
}
