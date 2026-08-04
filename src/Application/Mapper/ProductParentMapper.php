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

use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * Maps the identity-level variant fields (type + parent) onto the Product. These live on
 * `pr_products`, so they are set directly on the model rather than through the ContentPersister
 * pipeline.
 *
 * @internal This class should not be instantiated by a project.
 *           Create an own ProductMapper to extend the handler with custom logic.
 */
final class ProductParentMapper implements ProductMapperInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function mapProductData(ProductInterface $product, array $data): void
    {
        if (!\array_key_exists('type', $data)) {
            return;
        }

        Assert::string($data['type']);
        $product->setType($data['type']);

        if (!$product->isType(ProductInterface::TYPE_VARIANT)) {
            $product->setParent(null);

            return;
        }

        $parent = $data['parent'] ?? null;
        Assert::stringNotEmpty($parent);

        $product->setParent($this->productRepository->getOneBy(['uuid' => $parent]));
    }
}
