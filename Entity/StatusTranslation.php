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
 * StatusTranslation
 */
class StatusTranslation
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $languageCode;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Status
     */
    private $status;

    /**
     * Set name
     *
     * @param string $name
     * @return StatusTranslation
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
     * Set languageCode
     *
     * @param string $languageCode
     * @return StatusTranslation
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
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set status
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Status $status
     * @return StatusTranslation
     */
    public function setStatus(\Sulu\Bundle\Product\BaseBundle\Entity\Status $status)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Status 
     */
    public function getStatus()
    {
        return $this->status;
    }
}
