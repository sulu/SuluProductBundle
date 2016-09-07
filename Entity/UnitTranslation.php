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
 * UnitTranslation.
 */
class UnitTranslation
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
     * @var \Sulu\Bundle\ProductBundle\Entity\Unit
     */
    private $unit;

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return UnitTranslation
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
     * Set locale.
     *
     * @param string $locale
     *
     * @return UnitTranslation
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
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set unit.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Unit $unit
     *
     * @return UnitTranslation
     */
    public function setUnit(\Sulu\Bundle\ProductBundle\Entity\Unit $unit)
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Get unit.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Unit
     */
    public function getUnit()
    {
        return $this->unit;
    }
}
