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
 * AttributeTranslation
 */
class AttributeTranslation
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
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Attribute
     */
    private $attribute;

    /**
     * Set languageCode
     *
     * @param string $languageCode
     * @return AttributeTranslation
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
     * @return AttributeTranslation
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
     * Set attribute
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Attribute $attribute
     * @return AttributeTranslation
     */
    public function setAttribute(\Sulu\Bundle\Product\BaseBundle\Entity\Attribute $attribute)
    {
        $this->attribute = $attribute;
    
        return $this;
    }

    /**
     * Get attribute
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Attribute 
     */
    public function getAttribute()
    {
        return $this->attribute;
    }
}
