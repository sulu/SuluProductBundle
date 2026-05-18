<?php

declare(strict_types=1);

namespace Sulu\Product\Application\Mapper;

use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Model\ProductTranslation;

final class ProductDetailsMapper implements ProductMapperInterface
{
    public function mapProductData(ProductInterface $product, array $data): void
    {
        if (\array_key_exists('code', $data)) {
            $product->setCode($data['code'] ?: null);
        }

        $locale = $data['locale'] ?? null;
        if (null !== $locale && \array_key_exists('name', $data)) {
            $translation = $product->getTranslation($locale);
            if (null === $translation) {
                $translation = new ProductTranslation($product, $locale, $data['name'] ?? '');
                $product->addTranslation($translation);
            } else {
                $translation->setName($data['name'] ?? '');
            }
        }
    }
}
