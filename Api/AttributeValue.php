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

use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\UserInterface;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;
use JMS\Serializer\Annotation\ExclusionPolicy;

/**
 * The product class which will be exported to the API
 * @package Sulu\Bundle\ProductBundle\Api
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
        $this->setSelected(false);
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
     */
    public function getSelected()
    {
        $this->entity->getSelected();
    }

    /**
     * Sets the selected state of the attributeValue
     * @param $selected The selected state of the attributeValue
     */
    public function setSelected($selected)
    {
        $this->entity->setSelected($selected);
    }

    private function getTranslation()
    {
        $attributeValueTranslation = $this->entity->getTranslation($this->locale);
        if (!$attributeValueTranslation) {
            $attributeValueTranslation = new AttributeValueTranslation();
            $attributeValueTranslation->setLocale($this->locale);
            $attributeValueTranslation->setAttributeValue($this->entity);

            $this->entity->addTranslation($attributeValueTranslation);
        }
        return $attributeValueTranslation;
    }
}
