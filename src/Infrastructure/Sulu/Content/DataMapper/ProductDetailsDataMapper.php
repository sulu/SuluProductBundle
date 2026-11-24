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
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepositoryInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Factory\CategoryFactoryInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\InvalidMainCategoryException;
use Sulu\Product\Domain\Exception\InvalidProductStatusException;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Webmozart\Assert\Assert;

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
        private readonly CategoryFactoryInterface $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ?string $mainCategoryRootKey,
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

        if ($localizedDimensionContent instanceof ProductDimensionContentInterface
            && \array_key_exists('mainCategory', $data)
        ) {
            $this->mapMainCategory($localizedDimensionContent, $data['mainCategory']);
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

        if (\array_key_exists('status', $data) && \is_string($data['status'])) {
            $status = $data['status'];
            if (!\in_array($status, $this->allowedStatuses, true)) {
                throw new InvalidProductStatusException($status, $this->allowedStatuses);
            }
            $unlocalizedDimensionContent->setStatus($status);
        }

        $this->mapDetailsData($unlocalizedDimensionContent, $localizedDimensionContent, $data);
    }

    /**
     * The taxonomy mapper (priority 63) runs before this mapper (priority 10) and replaces the excerpt categories.
     * Mapping the main category after it keeps a removed main category from coming back with a stale list.
     */
    private function mapMainCategory(ProductDimensionContentInterface $localizedDimensionContent, mixed $mainCategoryId): void
    {
        Assert::nullOrInteger($mainCategoryId);

        $mainCategory = null === $mainCategoryId
            ? null
            : ($this->categoryFactory->create([$mainCategoryId])[0] ?? null);

        // A stored main category stays valid when the root key changes later, so publishing keeps working.
        if (null !== $mainCategory && $mainCategory !== $localizedDimensionContent->getMainCategory()) {
            $this->assertBelowRoot($mainCategory);
        }

        $localizedDimensionContent->setMainCategory($mainCategory);
    }

    private function assertBelowRoot(CategoryInterface $mainCategory): void
    {
        if (null === $this->mainCategoryRootKey) {
            return;
        }

        /** @var CategoryInterface|null $root */
        $root = $this->categoryRepository->findCategoryByKey($this->mainCategoryRootKey);

        if (null === $root
            || $mainCategory->getLft() <= $root->getLft()
            || $mainCategory->getRgt() >= $root->getRgt()
        ) {
            throw new InvalidMainCategoryException($mainCategory->getId(), $this->mainCategoryRootKey);
        }
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

        if (!$localizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        $locale = $localizedDimensionContent->getLocale();
        if (null === $locale) {
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

        $localizedDimensionContent->setDetailsData($localizedDetails);
        $unlocalizedDimensionContent->setDetailsData($unlocalizedDetails);
    }
}
