<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\Mapper;

use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Maps the identity-level variant fields (type + parent) onto the Product.
 * These live on `pr_products`, so they are set directly on the model rather
 * than through the ContentPersister pipeline.
 */
final class ProductParentMapper implements ProductMapperInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function mapProductData(ProductInterface $product, array $data): void
    {
        if (!$product instanceof Product) {
            return;
        }

        if (\array_key_exists('type', $data) && \is_string($data['type'])) {
            $product->setType($data['type']);
        }

        if (\array_key_exists('parent', $data) && \is_string($data['parent'])) {
            $parent = $this->productRepository->getOneBy(['uuid' => $data['parent']]);
            $product->setParent($parent);
        }
    }
}
