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
 * TemplateTranslation.
 */
class AttributeSetTranslation
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
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    private $attributeSet;

    /**
     * Set languageCode.
     *
     * @param string $languageCode
     *
     * @return AttributeSetTranslation
     */
    public function setLocale($languageCode)
    {
        $this->locale = $languageCode;

        return $this;
    }

    /**
     * Get languageCode.
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
     * @return AttributeSetTranslation
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
     * Set template.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeSet $template
     *
     * @return AttributeSetTranslation
     */
    public function setAttributeSet(\Sulu\Bundle\ProductBundle\Entity\AttributeSet $template)
    {
        $this->attributeSet = $template;

        return $this;
    }

    /**
     * Get template.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }
}
