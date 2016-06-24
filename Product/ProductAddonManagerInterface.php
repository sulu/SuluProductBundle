<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
