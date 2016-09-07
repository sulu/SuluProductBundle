<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute as ProductAttributeEntity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The ProductAttribute class which will be exported to the API.
 *
 * @ExclusionPolicy("all")
 */
class ProductAttribute extends ApiWrapper
{
    /**
     * @var string
     */
    protected $fallbackLocale;

    /**
     * @param ProductAttributeEntity $entity
     * @param string $locale
     * @param string $fallbackLocale
     */
    public function __construct(ProductAttributeEntity $entity, $locale, $fallbackLocale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * @VirtualProperty
     * @SerializedName("fallbackLocale")
     *
     * @return string
     */
    public function getFallbackLocale()
    {
        return $this->fallbackLocale;
    }

    /**
     * Returns the id of the Attribute.
     *
     * @return string
     *
     * @VirtualProperty
     * @SerializedName("attributeId")
     */
    public function getAttributeId()
    {
        return $this->getAttribute()->getId();
    }

    /**
     * Returns the attribute name.
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     *
     * @VirtualProperty
     * @SerializedName("attributeName")
     */
    public function getAttributeName()
    {
        return $this->getAttribute()->getTranslation()->getName();
    }

    /**
     * @VirtualProperty
     * @SerializedName("attributeLocale")
     *
     * @return string
     */
    public function getAttributeLocale()
    {
        return $this->getAttribute()->getTranslation()->getLocale();
    }

    /**
     * Returns the attribute type name.
     *
     * @return Sulu\Bundle\ProductBundle\Api\AttributeType
     *
     * @VirtualProperty
     * @SerializedName("attributeTypeName")
     */
    public function getAttributeTypeName()
    {
        return $this->getAttribute()->getType()->getName();
    }

    /**
     * @VirtualProperty
     * @SerializedName("attributeValueId")
     *
     * @return string
     */
    public function getAttributeValueId()
    {
        return $this->getAttributeValue()->getId();
    }

    /**
     * @VirtualProperty
     * @SerializedName("attributeValueName")
     *
     * @return string
     */
    public function getAttributeValueName()
    {
        return $this->getAttributeValue()->getTranslation()->getName();
    }

    /**
     * @VirtualProperty
     * @SerializedName("attributeValueLocale")
     *
     * @return string
     */
    public function getAttributeValueLocale()
    {
        return $this->getAttributeValue()->getTranslation()->getLocale();
    }

    /**
     * @VirtualProperty
     * @SerializedName("attributeKey")
     *
     * @return string
     */
    public function getAttributeKey()
    {
        return $this->getAttribute()->getKey();
    }

    /**
     * @return Attribute
     */
    public function getAttribute()
    {
        return new Attribute($this->entity->getAttribute(), $this->locale, $this->fallbackLocale);
    }

    /**
     * @return AttributeValue
     */
    public function getAttributeValue()
    {
        return new AttributeValue($this->entity->getAttributeValue(), $this->locale, $this->fallbackLocale);
    }
}
