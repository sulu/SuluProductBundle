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
 * AttributeType.
 */
class AttributeType
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $attributes;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->attributes = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add attributes.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Attribute $attributes
     *
     * @return AttributeType
     */
    public function addAttribute(\Sulu\Bundle\ProductBundle\Entity\Attribute $attributes)
    {
        $this->attributes[] = $attributes;

        return $this;
    }

    /**
     * Remove attributes.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Attribute $attributes
     */
    public function removeAttribute(\Sulu\Bundle\ProductBundle\Entity\Attribute $attributes)
    {
        $this->attributes->removeElement($attributes);
    }

    /**
     * Get attributes.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @var string
     */
    private $name;

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return AttributeType
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return AttributeType
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
}
