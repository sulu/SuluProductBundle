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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\InvalidProductStatusException;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductDetailsDataMapper implements DataMapperInterface
{
    /**
     * @param array<int, string> $allowedStatuses
     */
    public function __construct(
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
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

        $this->mapDetailsData($unlocalizedDimensionContent, $localizedDimensionContent, $data);
    }

    /**
     * @template T of DimensionContentInterface
     *
     * @param T $localizedDimensionContent
     * @param array<string, mixed> $data
     */
    private function mapDetailsData(
        ProductDimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!\array_key_exists('details', $data)) {
            return;
        }

        $details = \is_array($data['details']) ? $data['details'] : [];

        $locale = $localizedDimensionContent instanceof ProductDimensionContentInterface
            ? $localizedDimensionContent->getLocale()
            : null;

        if (!\is_string($locale)) {
            return;
        }

        $formMetadata = $this->formMetadataLoader->getMetadata(ProductInterface::FORM_KEY, $locale, []);

        if (!$formMetadata instanceof FormMetadata) {
            return;
        }

        $localizedDetails = [];
        $unlocalizedDetails = [];

        foreach ($formMetadata->getFlatFieldMetadata() as $property) {
            $parts = \explode('/', $property->getName(), 2);
            if ('details' !== $parts[0] || !isset($parts[1])) {
                continue;
            }

            $field = $parts[1];
            if (!\array_key_exists($field, $details)) {
                continue;
            }

            if ($property->isMultilingual()) {
                $localizedDetails[$field] = $details[$field];

                continue;
            }

            $unlocalizedDetails[$field] = $details[$field];
        }

        if ($localizedDimensionContent instanceof ProductDimensionContentInterface) {
            $localizedDimensionContent->setDetailsData($localizedDetails);
        }

        $unlocalizedDimensionContent->setDetailsData($unlocalizedDetails);
    }
}
