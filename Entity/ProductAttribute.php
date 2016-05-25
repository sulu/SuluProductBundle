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

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductAttribute
 */
class ProductAttribute
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\BaseProduct
     */
    private $product;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Attribute
     */
    private $attribute;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeValue
     */
    private $attributeValue;

    /**
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     * @return ProductAttribute
     */
    public function setProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $product)
    {
        $this->product = $product;
    
        return $this;
    }

    /**
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param \Sulu\Bundle\ProductBundle\Entity\Attribute $attribute
     * @return ProductAttribute
     */
    public function setAttribute(\Sulu\Bundle\ProductBundle\Entity\Attribute $attribute)
    {
        $this->attribute = $attribute;
    
        return $this;
    }

    /**
     * @return \Sulu\Bundle\ProductBundle\Entity\Attribute
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValue $attributeValue
     * @return ProductAttribute
     */
    public function setAttributeValue(\Sulu\Bundle\ProductBundle\Entity\AttributeValue $attributeValue)
    {
        $this->attributeValue = $attributeValue;

        return $this;
    }

    /**
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeValue
     */
    public function getAttributeValue()
    {
        return $this->attributeValue;
    }
}
