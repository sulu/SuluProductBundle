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
 * AttributeValueTranslation.
 */
class AttributeValueTranslation
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeValue
     */
    private $attributeValue;

    /**
     * Set locale.
     *
     * @param string $locale
     *
     * @return AttributeValueTranslation
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return AttributeValueTranslation
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * Set attributeValue.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValue $attributeValue
     *
     * @return AttributeValueTranslation
     */
    public function setAttributeValue(\Sulu\Bundle\ProductBundle\Entity\AttributeValue $attributeValue)
    {
        $this->attributeValue = $attributeValue;

        return $this;
    }

    /**
     * Get attributeValue.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeValue
     */
    public function getAttributeValue()
    {
        return $this->attributeValue;
    }
}
