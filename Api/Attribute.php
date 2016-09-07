<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Hateoas\Configuration\Annotation\Relation;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Sulu\Bundle\ProductBundle\Entity\Attribute as AttributeEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;
use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\Authentication\UserInterface;

/**
 * The Attribute class which will be exported to the API.
 *
 * @Relation("self", href="expr('/admin/api/attributes/' ~ object.getId())")
 * @ExclusionPolicy("all")
 */
class Attribute extends ApiWrapper
{
    /**
     * @var string
     */
    protected $fallbackLocale;

    /**
     * @param AttributeEntity $entity
     * @param string $locale
     * @param string $fallbackLocale
     */
    public function __construct(AttributeEntity $entity, $locale, $fallbackLocale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * Returns the id of the attribute.
     *
     * @VirtualProperty
     * @SerializedName("id")
     *
     * @return int
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the attribute.
     *
     * @VirtualProperty
     * @SerializedName("name")
     *
     * @return int
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the attribute.
     *
     * @param string $name The name of the attribute
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setName($name)
    {
        $this->getTranslation(false)->setName($name);

        return $this;
    }

    /**
     * Returns the type of the attribute.
     *
     * @VirtualProperty
     * @SerializedName("type")
     *
     * @return Sulu\Bundle\ProductBundle\Api\AttributeType
     */
    public function getType()
    {
        return new AttributeType($this->entity->getType(), $this->locale);
    }

    /**
     * Sets the type of the attribute.
     *
     * @param AtributeType $type The type of the attribute
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setType($type)
    {
        $this->entity->setType($type);

        return $this;
    }

    /**
     * Returns changed date of the attribute.
     *
     * @return DateTime
     */
    public function getChanged()
    {
        return $this->entity->getChanged();
    }

    /**
     * Sets the changed date of the attribute.
     *
     * @param $changed $changed date for the attribute
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setChanged($changed)
    {
        $this->entity->setChanged($changed);

        return $this;
    }

    /**
     * Sets the changer of the attribute.
     *
     * @param $changer changer for the attribute
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setChanger(UserInterface $changer)
    {
        $this->entity->setChanger($changer);

        return $this;
    }

    /**
     * Returns created date for the Attribute.
     *
     * @return \Date
     */
    public function getCreated()
    {
        return $this->entity->getCreated();
    }

    /**
     * Sets the created date for the attribute.
     *
     * @param $created created date for the attribute
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setCreated($created)
    {
        $this->entity->setCreated($created);

        return $this;
    }

    /**
     * Sets the creator of the attribute.
     *
     * @param $creator creator of the attribute
     *
     * @return Sulu\Bundle\ProductBundle\Api\Attribute
     */
    public function setCreator(UserInterface $creator)
    {
        $this->entity->setCreator($creator);

        return $this;
    }

    /**
     * Add attribute value.
     *
     * @param AttributeValueEntity $value
     *
     * @return Attribute
     */
    public function addValue(AttributeValueEntity $value)
    {
        $this->entity->addValue($value);

        return $this;
    }

    /**
     * Returns the translation.
     *
     * @param bool $useFallback
     *
     * @return AttributeTranslation
     */
    public function getTranslation($useFallback = true)
    {
        $attributeTranslation = $this->getTranslationByLocale($this->locale);

        if (!$attributeTranslation && $useFallback) {
            $attributeTranslation = $this->getTranslationByLocale($this->fallbackLocale);
        }

        if (!$attributeTranslation) {
            $attributeTranslation = new AttributeTranslation();
            $attributeTranslation->setLocale($this->locale);
            $attributeTranslation->setAttribute($this->entity);

            $this->entity->addTranslation($attributeTranslation);
        }

        return $attributeTranslation;
    }

    /**
     * Returns the translation with the given locale.
     *
     * @param string $locale
     *
     * @return AttributeTranslation
     */
    private function getTranslationByLocale($locale)
    {
        $attributeTranslation = null;

        foreach ($this->entity->getTranslations() as $translation) {
            if ($translation->getLocale() == $locale) {
                $attributeTranslation = $translation;
                break;
            }
        }

        return $attributeTranslation;
    }

    /**
    * @VirtualProperty
    * @SerializedName("key")
    *
    * @return string
    */
    public function getKey()
    {
        return $this->getEntity()->getKey();
    }

    /**
     * @param string $key
     *
     * @return self
     */
    public function setKey($key)
    {
        $this->getEntity()->setKey($key);

        return $this;
    }
}
