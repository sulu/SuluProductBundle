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
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The product class which will be exported to the API.
 *
 * @Relation("self", href="expr('/api/admin/attributes/' ~ object.getId() ~ '/values')")
 * @ExclusionPolicy("all")
 */
class AttributeValue extends ApiWrapper
{
    /**
     * @var string
     */
    protected $fallbackLocale;

    /**
     * @param AttributeValueEntity $entity The attributeValue to wrap
     * @param string $locale The locale of this attributeValue
     * @param string $fallbackLocale
     */
    public function __construct(AttributeValueEntity $entity, $locale, $fallbackLocale)
    {
        $this->entity = $entity;
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * Returns the id of the attributeValue.
     *
     * @return int
     *
     * @VirtualProperty
     * @SerializedName("attributeValueId")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the attributeValue.
     *
     * @return string The name of the attributeValue
     *
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the attributeValue.
     *
     * @param string $name The name of the attributeValue
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * Sets the attribute.
     *
     * @param Attribute $attribute
     */
    public function setAttribute($attribute)
    {
        $this->entity->setAttribute($attribute);
    }

    /**
     * Returns the translation with the given locale.
     *
     * @return AttributeValueTranslation
     */
    public function getTranslation()
    {
        $attributeValueTranslation = $this->getTranslationByLocale($this->locale);

        if (!$attributeValueTranslation) {
            $attributeValueTranslation = $this->getTranslationByLocale($this->fallbackLocale);
        }

        if (!$attributeValueTranslation) {
            $attributeValueTranslation = new AttributeValueTranslation();
            $attributeValueTranslation->setLocale($this->locale);
            $attributeValueTranslation->setAttributeValue($this->entity);

            $this->entity->addTranslation($attributeValueTranslation);
        }

        return $attributeValueTranslation;
    }

    /**
     * Returns the translation with the given locale.
     *
     * @param string $locale
     *
     * @return AttributeValueTranslation
     */
    private function getTranslationByLocale($locale)
    {
        $attributeValueTranslation = null;

        foreach ($this->entity->getTranslations() as $translation) {
            if ($translation->getLocale() == $locale) {
                $attributeValueTranslation = $translation;
                break;
            }
        }

        return $attributeValueTranslation;
    }
}
