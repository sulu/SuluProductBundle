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
 * TaxClassTranslation.
 */
class TaxClassTranslation
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
     * @var \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    private $taxClass;

    /**
     * Set locale.
     *
     * @param string $locale
     *
     * @return TaxClassTranslation
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
     * @return TaxClassTranslation
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
     * Set taxClass.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass
     *
     * @return TaxClassTranslation
     */
    public function setTaxClass(\Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass = null)
    {
        $this->taxClass = $taxClass;

        return $this;
    }

    /**
     * Get taxClass.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    public function getTaxClass()
    {
        return $this->taxClass;
    }
}
