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
 * TemplateTranslation
 */
class AttributeSetTranslation
{
    /**
     * @var string
     */
    private $languageCode;

    /**
     * @var string
     */
    private $name;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    private $attributeSet;

    /**
     * Set languageCode
     *
     * @param string $languageCode
     * @return AttributeSetTranslation
     */
    public function setLanguageCode($languageCode)
    {
        $this->languageCode = $languageCode;
    
        return $this;
    }

    /**
     * Get languageCode
     *
     * @return string 
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return AttributeSetTranslation
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
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
     * Set template
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeSet $template
     * @return AttributeSetTranslation
     */
    public function setAttributeSet(\Sulu\Bundle\ProductBundle\Entity\AttributeSet $template)
    {
        $this->attributeSet = $template;
    
        return $this;
    }

    /**
     * Get template
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeSet
     */
    public function getAttributeSet()
    {
        return $this->attributeSet;
    }
}
