<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Sulu\Bundle\ProductBundle\Entity\ProductInterface as Entity;
use Sulu\Bundle\CoreBundle\Entity\ApiEntityWrapper;
use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;

/**
 * The product class which will be exported to the API
 * @package Sulu\Bundle\ProductBundle\Api
 */
class Product extends ApiEntityWrapper
{
    /**
     * @param Entity $product The product to wrap
     * @param string $locale The locale of this product
     */
    public function __construct(Entity $product, $locale) {
        $this->entity = $product;
        $this->locale = $locale;
    }

    /**
     * Returns the name of the product
     * @return string The name of the product
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->entity->getTranslation($this->locale)->getName();
    }

    /**
     * Returns the code of the product
     * @return string The code of the product
     * @VirtualProperty
     * @SerializedName("code")
     */
    public function getCode()
    {
        return $this->entity->getCode();
    }

    /**
     * Returns the number of the product
     * @return string The number of the product
     * @VirtualProperty
     * @SerializedName("number")
     */
    public function getNumber()
    {
        return $this->entity->getNumber();
    }

    /**
     * Returns the manufacturer of the product
     * @return string The manufacturer of the product
     * @VirtualProperty
     * @SerializedName("manufacturer")
     */
    public function getManufacturer()
    {
        return $this->entity->getManufacturer();
    }

    /**
     * Returns the type of the product
     * @return Type The type of the product
     * @VirtualProperty
     * @SerializedName("type")
     */
    public function getType()
    {
        return new Type($this->entity->getType(), $this->locale);
    }

    /**
     * Returns the status of the product
     * @return Status The status of the product
     * @VirtualProperty
     * @SerializedName("status")
     */
    public function getStatus()
    {
        return new Status($this->entity->getStatus(), $this->locale);
    }
} 
