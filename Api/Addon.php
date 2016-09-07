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
use Sulu\Bundle\ProductBundle\Entity\Addon as AddonEntity;
use Sulu\Bundle\ProductBundle\Entity\AddonPrice;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;
use Sulu\Component\Rest\ApiWrapper;

/**
 * @ExclusionPolicy("all")
 */
class Addon extends ApiWrapper
{
    /**
     * @var ProductFactoryInterface
     */
    private $productFactory;

    /**
     * @param AddonEntity $entity
     * @param ProductFactoryInterface $productFactory
     * @param string $locale
     */
    public function __construct(
        AddonEntity $entity,
        ProductFactoryInterface $productFactory,
        $locale
    ) {
        $this->entity = $entity;
        $this->locale = $locale;
        $this->productFactory = $productFactory;
    }

    /**
     * Returns the id of the addon.
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
     * @VirtualProperty
     * @SerializedName("addon")
     *
     * @return ApiProductInterface
     */
    public function getAddonProduct()
    {
        return $this->productFactory->createApiEntity($this->entity->getAddon(), $this->locale);
    }

    /**
     * @VirtualProperty
     * @SerializedName("prices")
     *
     * @return AddonPrice[]
     */
    public function getPrices()
    {
        return $this->entity->getAddonPrices();
    }
}
