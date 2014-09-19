<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Hateoas\Configuration\Annotation\Relation;

use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;

use Sulu\Bundle\ProductBundle\Entity\Attribute as AttributeEntity;
use Sulu\Bundle\ProductBundle\Api\Attribute as Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Api\AttributeType;
use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\UserInterface;

/**
 * The Attribute class which will be exported to the API
 *
 * @package Sulu\Bundle\ProductBundle\Api
 * @Relation("self", href="expr('/api/admin/attributes/' ~ object.getId())")
 * @ExclusionPolicy("all")
 */
class Attribute extends ApiWrapper
{
    /**
     * @param Entity $entity
     * @param string $locale
     */
    public function __construct(AttributeEntity $entity, $locale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
    }

    /**
     * Returns the id of the Attribute
     *
     * @VirtualProperty
     * @SerializedName("id")
     * @return int
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the Attribute
     *
     * @VirtualProperty
     * @SerializedName("name")
     * @return int
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the attribute
     * @param string $name The name of the attribute
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
        return $this;
    }

    /**
     * Returns the type of the Attribute
     *
     * @VirtualProperty
     * @SerializedName("type")
     * @return Sulu\Bundle\ProductBundle\Api\AttributeType
     */
    public function getType()
    {
        return new AttributeType($this->entity->getType(), $this->locale);
    }

    /**
     * Sets the type of the attribute
     * @param AtributeType $type The type of the attribute
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setType($type)
    {
        $this->entity->setType($type);
        return $this;
    }

    /**
     * Returns changed date of the Attribute
     *
     * @return DateTime
     */
    public function getChanged()
    {
        return $this->entity->getChanged();
    }

    /**
     * Sets the changed date of the attribute
     * @param $changed $changed date for the attribute
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setChanged($changed)
    {
        $this->entity->setChanged($changed);
        return $this;
    }

    /**
     * Sets the changer of the attribute
     * @param $changer changer for the attribute
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setChanger(UserInterface $changer)
    {
        $this->entity->setChanger($changer);
        return $this;
    }

    /**
     * Returns created date for the Attribute
     *
     * @return \Date
     */
    public function getCreated()
    {
        return $this->entity->getCreated();
    }

    /**
     * Sets the created date for the attribute
     * @param $created created date for the attribute
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setCreated($created)
    {
        $this->entity->setCreated($created);
        return $this;
    }

    /**
     * Sets the creator of the attribute
     * @param $creator creator of the attribute
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setCreator(UserInterface $creator)
    {
        $this->entity->setCreator($creator);
        return $this;
    }

    /**
     * Add value
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\AttributeValue $value
     * @return Attribute
     */
    public function addValue(Sulu\Bundle\ProductBundle\Entity\AttributeValue $value)
    {
        $this->entity->addValue($value);
        return $this;
    }

    /**
     * Returns the translation with the given locale
     * @param string $locale The locale to return
     * @return AttributeTranslation
     */
    public function getTranslation()
    {
        $fallback = null;
        foreach ($this->entity->translations as $translation) {
            if ($translation->getName() != null) {
                $fallback = $translation;
            }
            if ($translation->getLocale() == $this->locale) {
                return $translation;
            }
        }

        if ($fallback) {
            return $fallback;
        }

        // Still no translation found
        $translation = new AttributeTranslation();
        $translation->setLocale($this->locale);
        $translation->setAttribute($this->entity);

        $this->entity->addTranslation($translation);
        return $translation;
    }
}
