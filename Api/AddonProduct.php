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
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ProductBundle\Entity\AddonPrice as AddonPriceEntity;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Product\ProductFactory;
use Sulu\Bundle\ProductBundle\Product\ProductLocaleManager;
use Sulu\Bundle\ProductBundle\Util\PriceFormatter;

/**
 * @ExclusionPolicy("all")
 */
class AddonProduct extends Product implements ApiAddonProductInterface
{
    /**
     * @var AddonPriceEntity[]
     */
    private $addonPrices;

    /**
     * @param ProductInterface $product The product to wrap
     * @param string $locale The locale of this product
     * @param PriceFormatter $priceFormatter
     * @param ProductLocaleManager $productLocaleManager
     * @param ProductFactory $productFactory
     * @param AccountManager|null $accountManager
     * @param array $addonPrices
     */
    public function __construct(
        ProductInterface $product,
        $locale,
        PriceFormatter $priceFormatter,
        ProductLocaleManager $productLocaleManager,
        ProductFactory $productFactory,
        AccountManager $accountManager = null,
        array $addonPrices = []
    ) {
        parent::__construct(
            $product,
            $locale,
            $priceFormatter,
            $productLocaleManager,
            $productFactory,
            $accountManager
        );

        $this->setAddonPrices($addonPrices);
    }

    /**
     * @param array $addonPrices
     *
     * @return $this
     */
    public function setAddonPrices(array $addonPrices)
    {
        $this->addonPrices = $addonPrices;

        return $this;
    }

    /**
     * @VirtualProperty
     * @SerializedName("addonPrices")
     *
     * {@inheritdoc}
     */
    public function getAddonPrices()
    {
        $addonPrices = [];
        if ($this->addonPrices) {
            foreach ($this->addonPrices as $addonPrice) {
                $addonPrices[] = new AddonPrice($addonPrice, $this->locale, $this->priceFormatter);
            }
        }

        return $addonPrices;
    }
}
