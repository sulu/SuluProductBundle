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
use Sulu\Product\Domain\Model\ProductTranslation;

final class ProductDetailsMapper implements ProductMapperInterface
{
    public function mapProductData(ProductInterface $product, array $data): void
    {
        /** @var string|null $code */
        $code = $data['code'] ?? null;
        /** @var string|null $locale */
        $locale = $data['locale'] ?? null;

        $product->setCode($code);

        if (null !== $locale && \array_key_exists('name', $data)) {
            $translation = $product->getTranslation($locale);
            /** @var string $name */
            $name = $data['name'] ?? '';
            if (null === $translation) {
                $translation = new ProductTranslation($product, $locale, $name);
                $product->addTranslation($translation);
            } else {
                $translation->setName($name);
            }
        }
    }
}
