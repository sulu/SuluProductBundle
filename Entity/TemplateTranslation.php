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
 * TemplateTranslation
 */
class TemplateTranslation
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
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Template
     */
    private $template;

    /**
     * Set languageCode
     *
     * @param string $languageCode
     * @return TemplateTranslation
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
     * @return TemplateTranslation
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
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Template $template
     * @return TemplateTranslation
     */
    public function setTemplate(\Sulu\Bundle\Product\BaseBundle\Entity\Template $template)
    {
        $this->template = $template;
    
        return $this;
    }

    /**
     * Get template
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Template 
     */
    public function getTemplate()
    {
        return $this->template;
    }
}
