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
 * Attribute.
 */
interface AttributeInterface
{
    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Attribute
     */
    public function setCreated($created);

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated();

    /**
     * Set changed.
     *
     * @param \DateTime $changed
     *
     * @return Attribute
     */
    public function setChanged($changed);

    /**
     * Get changed.
     *
     * @return \DateTime
     */
    public function getChanged();

    /**
     * Get id.
     *
     * @return int
     */
    public function getId();

    /**
     * Add values.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValue $values
     *
     * @return Attribute
     */
    public function addValue(\Sulu\Bundle\ProductBundle\Entity\AttributeValue $values);

    /**
     * Set type.
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeType $type
     *
     * @return Attribute
     */
    public function setType(\Sulu\Bundle\ProductBundle\Entity\AttributeType $type);

    /**
     * Get type.
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\AttributeType
     */
    public function getType();

    /**
     * Set changer.
     *
     * @param \Sulu\Bundle\SecurityBundle\Entity\User $changer
     *
     * @return Attribute
     */
    public function setChanger(\Sulu\Bundle\SecurityBundle\Entity\User $changer = null);

    /**
     * Set creator.
     *
     * @param \Sulu\Bundle\SecurityBundle\Entity\User $creator
     *
     * @return Attribute
     */
    public function setCreator(\Sulu\Bundle\SecurityBundle\Entity\User $creator = null);
}
