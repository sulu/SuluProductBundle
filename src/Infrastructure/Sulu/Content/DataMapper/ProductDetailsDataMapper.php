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
use Sulu\Product\Domain\Exception\InvalidProductStatusException;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductDetailsDataMapper implements DataMapperInterface
{
    /**
     * @param array<int, string> $allowedStatuses
     */
    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly array $allowedStatuses,
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
            if (null !== $code) {
                $excludeUuid = $unlocalizedDimensionContent->getResource()->getUuid();
                if ($this->productRepository->existBy(['code' => $code, 'excludeUuid' => $excludeUuid])) {
                    throw new ProductCodeNotUniqueException($code);
                }
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

        if (\array_key_exists('status', $data)) {
            $status = \is_string($data['status']) ? $data['status'] : null;
            if (null !== $status && !\in_array($status, $this->allowedStatuses, true)) {
                throw new InvalidProductStatusException($status, $this->allowedStatuses);
            }
            $unlocalizedDimensionContent->setStatus($status);
        }

        if (\array_key_exists('shortDescription', $data)
            && $localizedDimensionContent instanceof ProductDimensionContentInterface
        ) {
            $shortDescription = \is_string($data['shortDescription']) ? $data['shortDescription'] : null;
            $localizedDimensionContent->setShortDescription($shortDescription);
        }

        if (\array_key_exists('image', $data)) {
            $value = $data['image'];
            if (\is_array($value)) {
                $value = $value['id'] ?? null;
            }
            $mediaId = null;
            if (\is_int($value)) {
                $mediaId = $value;
            } elseif (\is_string($value) && \is_numeric($value)) {
                $mediaId = (int) $value;
            }
            $unlocalizedDimensionContent->setImage($mediaId);
        }

        if (\array_key_exists('documents', $data)) {
            $value = $data['documents'];
            $ids = null;
            if (\is_array($value)) {
                $rawIds = (\array_key_exists('ids', $value) && \is_array($value['ids'])) ? $value['ids'] : $value;
                $ids = [];
                foreach ($rawIds as $rawId) {
                    if (\is_int($rawId)) {
                        $ids[] = $rawId;
                    } elseif (\is_string($rawId) && \is_numeric($rawId)) {
                        $ids[] = (int) $rawId;
                    }
                }
            }
            $unlocalizedDimensionContent->setDocuments($ids);
        }
    }
}
