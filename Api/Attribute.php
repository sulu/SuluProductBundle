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

use Sulu\Bundle\ProductBundle\Entity\Attribute as Entity;
use Sulu\Component\Rest\ApiWrapper;

use Hateoas\Configuration\Annotation\Relation;

use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

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
     * @param Entity $type
     * @param string $locale
     */
    public function __construct(Entity $type, $locale)
    {
        $this->entity = $type;
        $this->locale = $locale;
    }

    /**
     * Returns the id of the Attribute
     *
     * @VirtualProperty
     * @SerializedName("id")
     * @Groups({"fullAttribute", "partialAttribute"})
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
     * @Groups({"fullAttribute", "partialAttribute"})
     * @return int
     */
    public function getName()
    {
        return $this->entity->getTranslation($this->locale)->getName();
    }
}
