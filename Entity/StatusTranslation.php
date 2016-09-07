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
 * StatusTranslation.
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
    private $locale;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Status
     */
    private $status;

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return StatusTranslation
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
     * Set languageCode.
     *
     * @param string $languageCode
     *
     * @return StatusTranslation
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
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set status.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Status $status
     *
     * @return StatusTranslation
     */
    public function setStatus(\Sulu\Bundle\ProductBundle\Entity\Status $status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }
}
