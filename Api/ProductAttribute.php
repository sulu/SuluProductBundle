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

use Sulu\Bundle\ProductBundle\Entity\ProductAttribute as ProductAttributeEntity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The ProductAttribute class which will be exported to the API
 *
 * @package Sulu\Bundle\ProductBundle\Api
 * @ExclusionPolicy("all")
 */
class ProductAttribute extends ApiWrapper
{
    /**
     * @param AttributeEntity $entity
     * @param string $locale
     */
    public function __construct(ProductAttributeEntity $entity, $locale)
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
     * Returns the value
     *
     * @VirtualProperty
     * @SerializedName("value")
     * @return string
     */
    public function getValue()
    {
        return $this->entity->getValue();
    }
}
