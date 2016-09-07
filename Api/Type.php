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
use Sulu\Bundle\ProductBundle\Entity\Type as TypeEntity;
use Sulu\Component\Rest\ApiWrapper;

/**
 * @ExclusionPolicy("all")
 */
class Type extends ApiWrapper
{
    /**
     * @param TypeEntity $type
     * @param string $locale
     */
    public function __construct(TypeEntity $type, $locale)
    {
        $this->entity = $type;
        $this->locale = $locale;
    }

    /**
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
     * @VirtualProperty
     * @SerializedName("translationKey")
     *
     * @return string
     */
    public function getTranslationKey()
    {
        return $this->entity->getTranslationKey();
    }
}
