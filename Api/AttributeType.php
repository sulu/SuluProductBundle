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

use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Sulu\Bundle\ProductBundle\Entity\AttributeType as AttribureTypeEntity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The AttributeType class which will be exported to the API.
 *
 * @ExclusionPolicy("all")
 */
class AttributeType extends ApiWrapper
{
    /**
     * @param Entity $type
     * @param string $locale
     */
    public function __construct(AttribureTypeEntity $type, $locale)
    {
        $this->entity = $type;
        $this->locale = $locale;
    }

    /**
     * The id of the type.
     *
     * @return int
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * The name of the type.
     *
     * @return string
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->entity->getName();
    }
}
