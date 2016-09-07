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
 * ProductTranslation.
 */
class ProductTranslation
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
     * @var string
     */
    private $shortDescription;

    /**
     * @var string
     */
    private $longDescription;

    /**
     * @var int
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    private $product;

    /**
     * Set languageCode.
     *
     * @param string $languageCode
     *
     * @return ProductTranslation
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
     * @return ProductTranslation
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
     * Set shortDescription.
     *
     * @param string $shortDescription
     *
     * @return ProductTranslation
     */
    public function setShortDescription($shortDescription)
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    /**
     * Get shortDescription.
     *
     * @return string
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * Set longDescription.
     *
     * @param string $longDescription
     *
     * @return ProductTranslation
     */
    public function setLongDescription($longDescription)
    {
        $this->longDescription = $longDescription;

        return $this;
    }

    /**
     * Get longDescription.
     *
     * @return string
     */
    public function getLongDescription()
    {
        return $this->longDescription;
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
     * Set product.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\ProductInterface $product
     *
     * @return ProductTranslation
     */
    public function setProduct(\Sulu\Bundle\ProductBundle\Entity\ProductInterface $product)
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get product.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }
}
