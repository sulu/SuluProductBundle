<?php

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\ProductBundle\Api\Addon;

interface ProductAddonManagerInterface
{
    /**
     * @param int $id
     * @param string $locale
     *
     * @return Addon[]
     */
    public function findAddonsByProductIdAndLocale($id, $locale);
}
