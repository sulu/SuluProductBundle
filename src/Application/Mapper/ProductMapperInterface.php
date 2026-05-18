<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\Mapper;

use Sulu\Product\Domain\Model\ProductInterface;

interface ProductMapperInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function mapProductData(ProductInterface $product, array $data): void;
}
