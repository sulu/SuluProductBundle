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

use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\UserInterface;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;

/**
 * The product class which will be exported to the API
 *
 * @package Sulu\Bundle\ProductBundle\Api
 * @Relation("self", href="expr('/api/admin/attributes/' ~ object.getId() ~ '/values')")
 * @ExclusionPolicy("all")
 */
class AttributeValue extends ApiWrapper
{
    /**
     * @param Entity $attributeValue The attributeValue to wrap
     * @param string $locale The locale of this attributeValue
     */
    public function __construct(AttributeValueEntity $entity, $locale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
    }

    /**
     * Returns the id of the attributeValue
     * @return int
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the attributeValue
     * @return string The name of the attributeValue
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the attributeValue
     * @param string $name The name of the attributeValue
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * Sets the attribute
     * @param Attribute $attribute
     */
    public function setAttribute($attribute)
    {
        $this->entity->setAttribute($attribute);
    }

    /**
     * Gets the selected state of the attributeValue
     * @param $selected The selected state of the attributeValue
     * @VirtualProperty
     * @SerializedName("selected")
     */
    public function getSelected()
    {
        return $this->entity->getSelected();
    }

    /**
     * Sets the selected state of the attributeValue
     * @param $selected The selected state of the attributeValue
     */
    public function setSelected($selected)
    {
        if (!$selected) {
            $selected = false;
        }
        $this->entity->setSelected($selected);
    }

    /**
     * Returns the translation with the given locale
     * @param string $locale The locale to return
     * @return AttributeValueTranslation
     */
    public function getTranslation()
    {
        foreach ($this->entity->translations as $translation) {
            if ($translation->getLocale() == $this->locale) {
                return $translation;
            }
        }
        $translation = new AttributeValueTranslation();
        $translation->setLocale($this->locale);
        $translation->setAttributeValue($this->entity);

        $this->entity->addTranslation($translation);
        return $translation;
    }
}
