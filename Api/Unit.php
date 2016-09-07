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
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Sulu\Bundle\ProductBundle\Entity\Unit as Entity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * The unit class which will be exported to the api.
 *
 * @ExclusionPolicy("all")
 */
class Unit extends ApiWrapper
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
     * The id of the type.
     *
     * @VirtualProperty
     * @SerializedName("id")
     *
     * @return int The id of the type
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * The name of the type.
     *
     * @VirtualProperty
     * @SerializedName("name")
     * @Groups({"cart"})
     *
     * @return int The name of the type
     */
    public function getName()
    {
        if (!$this->entity->getTranslation($this->locale)) {
            return null;
        }

        return $this->entity->getTranslation($this->locale)->getName();
    }
}
