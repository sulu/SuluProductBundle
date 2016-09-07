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

use Doctrine\Common\Collections\ArrayCollection;
use Sulu\Component\Persistence\Model\TimestampableInterface;
use Sulu\Component\Security\Authentication\UserInterface;

/**
 * Attribute.
 */
class Attribute implements TimestampableInterface
{
    const ATTRIBUTE_TYPE_TEXT = 1;
    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $changed;

    /**
     * @var int
     */
    private $id;

    /**
     * @var ArrayCollection
     */
    public $translations;

    /**
     * @var ArrayCollection
     */
    private $values;

    /**
     * @var ArrayCollection
     */
    private $productAttributes;

    /**
     * @var AttributeType
     */
    private $type;

    /**
     * @var UserInterface
     */
    private $changer;

    /**
     * @var UserInterface
     */
    private $creator;

    /**
     * @var string
     */
    private $key;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->values = new ArrayCollection();
        $this->productAttributes = new ArrayCollection();
    }

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return Attribute
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set changed.
     *
     * @param \DateTime $changed
     *
     * @return Attribute
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;

        return $this;
    }

    /**
     * Get changed.
     *
     * @return \DateTime
     */
    public function getChanged()
    {
        return $this->changed;
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
     * Set id.
     *
     * @param int $id
     *
     * @return Attribute
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Add translations.
     *
     * @param AttributeTranslation $translations
     *
     * @return Attribute
     */
    public function addTranslation(AttributeTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * Remove translations.
     *
     * @param AttributeTranslation $translations
     */
    public function removeTranslation(AttributeTranslation $translations)
    {
        $this->translations->removeElement($translations);
    }

    /**
     * Get translations.
     *
     * @return ArrayCollection
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * Add values.
     *
     * @param AttributeValue $values
     *
     * @return Attribute
     */
    public function addValue(AttributeValue $values)
    {
        $this->values[] = $values;

        return $this;
    }

    /**
     * Remove values.
     *
     * @param AttributeValue $values
     */
    public function removeValue(AttributeValue $values)
    {
        $this->values->removeElement($values);
    }

    /**
     * Get values.
     *
     * @return ArrayCollection
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Add productAttributes.
     *
     * @param ProductAttribute $productAttributes
     *
     * @return Attribute
     */
    public function addProductAttribute(ProductAttribute $productAttributes)
    {
        $this->productAttributes[] = $productAttributes;

        return $this;
    }

    /**
     * Remove productAttributes.
     *
     * @param ProductAttribute $productAttributes
     */
    public function removeProductAttribute(ProductAttribute $productAttributes)
    {
        $this->productAttributes->removeElement($productAttributes);
    }

    /**
     * Get productAttributes.
     *
     * @return ArrayCollection
     */
    public function getProductAttributes()
    {
        return $this->productAttributes;
    }

    /**
     * Set type.
     *
     * @param AttributeType $type
     *
     * @return Attribute
     */
    public function setType(AttributeType $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return AttributeType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set changer.
     *
     * @param UserInterface $changer
     *
     * @return Attribute
     */
    public function setChanger(UserInterface $changer = null)
    {
        $this->changer = $changer;

        return $this;
    }

    /**
     * Get changer.
     *
     * @return UserInterface
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator.
     *
     * @param UserInterface $creator
     *
     * @return Attribute
     */
    public function setCreator(UserInterface $creator = null)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get creator.
     *
     * @return UserInterface
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $key
     *
     * @return self
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }
}
