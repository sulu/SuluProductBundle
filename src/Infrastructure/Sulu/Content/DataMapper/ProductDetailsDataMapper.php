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

namespace Sulu\Product\Infrastructure\Sulu\Content\DataMapper;

use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductDetailsDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$unlocalizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        if ($localizedDimensionContent instanceof ProductDimensionContentInterface
            && \array_key_exists('title', $data)
        ) {
            /** @var string|null $title */
            $title = $data['title'];
            $localizedDimensionContent->setTitle($title);
        }

        if (\array_key_exists('code', $data)) {
            /** @var string|null $code */
            $code = \is_string($data['code']) ? $data['code'] : null;
            $currentCode = $unlocalizedDimensionContent->getCode();
            if (null !== $code && $code !== $currentCode && $this->productRepository->existBy(['code' => $code])) {
                throw new ProductCodeNotUniqueException($code);
            }
            $unlocalizedDimensionContent->setCode($code);
        }

        if (\array_key_exists('externalIdentifier', $data)) {
            /** @var string|null $externalIdentifier */
            $externalIdentifier = $data['externalIdentifier'];
            $unlocalizedDimensionContent->setExternalIdentifier($externalIdentifier);
        }

        if (\array_key_exists('productFamily', $data) && null !== $data['productFamily']) {
            /** @var string $productFamilyUuid */
            $productFamilyUuid = $data['productFamily'];
            $productFamily = $this->productFamilyRepository->getOneBy(['uuid' => $productFamilyUuid]);
            $unlocalizedDimensionContent->setProductFamily($productFamily);
        }

    }
}
