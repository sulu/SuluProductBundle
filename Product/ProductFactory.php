<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ProductBundle\Api\Product as ApiProduct;
use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Util\PriceFormatter;

class ProductFactory implements ProductFactoryInterface
{
    /**
     * @var AccountManager
     */
    protected $accountManager;

    /**
     * @var PriceFormatter
     */
    protected $priceFormatter;

    /**
     * @var ProductLocaleManager
     */
    protected $productLocaleManager;

    /**
     * @param AccountManager $accountManager
     * @param PriceFormatter $priceFormatter
     * @param ProductLocaleManager $productLocaleManager
     */
    public function __construct(
        AccountManager $accountManager = null,
        PriceFormatter $priceFormatter,
        ProductLocaleManager $productLocaleManager
    ) {
        $this->accountManager = $accountManager;
        $this->priceFormatter = $priceFormatter;
        $this->productLocaleManager = $productLocaleManager;
    }

    /**
     * {@inheritdoc}
     */
    public function createEntity()
    {
        return new Product();
    }

    /**
     * {@inheritdoc}
     */
    public function createApiEntity(ProductInterface $product, $locale)
    {
        return new ApiProduct(
            $product,
            $locale,
            $this->priceFormatter,
            $this->productLocaleManager,
            $this->accountManager
        );
    }
}
