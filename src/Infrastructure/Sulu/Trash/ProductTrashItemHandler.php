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

namespace Sulu\Product\Infrastructure\Sulu\Trash;

use Doctrine\Common\Collections\ArrayCollection;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Event\ProductTranslationRestoredEvent;
use Sulu\Product\Domain\Exception\ProductVariantParentNotFoundException;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class ProductTrashItemHandler implements
    StoreTrashItemHandlerInterface,
    RestoreTrashItemHandlerInterface,
    RestoreConfigurationProviderInterface
{
    public function __construct(
        private TrashItemRepositoryInterface $trashItemRepository,
        private ProductRepositoryInterface $productRepository,
        private ContentNormalizerInterface $contentNormalizer,
        private ContentMergerInterface $contentMerger,
        private ContentPersisterInterface $contentPersister,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public static function getResourceKey(): string
    {
        return ProductInterface::RESOURCE_KEY;
    }

    public function store(object $resource, array $options = []): TrashItemInterface
    {
        Assert::isInstanceOf($resource, ProductInterface::class);

        $product = $resource;
        $restoreType = $options['locale'] ?? null ? 'translation' : null;
        /** @var string|null $locale */
        $locale = 'translation' === $restoreType ? $options['locale'] : null;

        [$dimensionContents, $titles] = $this->normalizeDimensionContents($product, $locale);

        $data = [
            'dimensionContents' => $dimensionContents,
            'type' => $product->getType(),
            'parent' => $product->getParent()?->getUuid(),
            'position' => $product->getPosition(),
        ];

        // Variants ride on their parent's trash item, so restoring the parent brings them back.
        if (null === $restoreType && $product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            $data['variants'] = [];
            foreach ($this->productRepository->findBy(['parent' => $product->getUuid()]) as $variant) {
                [$variantDimensionContents] = $this->normalizeDimensionContents($variant, null);
                $data['variants'][] = [
                    'uuid' => $variant->getUuid(),
                    'position' => $variant->getPosition(),
                    'dimensionContents' => $variantDimensionContents,
                ];
            }
        }

        return $this->trashItemRepository->create(
            ProductInterface::RESOURCE_KEY,
            $product->getUuid(),
            $titles,
            $data,
            $restoreType,
            $options,
            ProductAdmin::SECURITY_CONTEXT,
            null, // TODO add Security
            $product->getUuid(),
        );
    }

    /**
     * @param array{} $restoreFormData
     */
    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        $restoreData = $trashItem->getRestoreData();
        $productUuid = $trashItem->getResourceId();

        $parent = null;
        $parentUuid = $restoreData['parent'] ?? null;
        if (\is_string($parentUuid)) {
            $parent = $this->productRepository->findOneBy(['uuid' => $parentUuid])
                ?? throw new ProductVariantParentNotFoundException($productUuid, $parentUuid);
        }

        $product = $this->findOrCreateProduct($productUuid);
        $product->setParent($parent);

        if (\is_string($restoreData['type'] ?? null)) {
            $product->setType($restoreData['type']);
        }

        // A translation restore keeps the current position, which may have changed by a reorder since.
        if ('translation' !== $trashItem->getRestoreType() && \is_int($restoreData['position'] ?? null)) {
            $product->setPosition($restoreData['position']);
        }

        $dimensionContents = $restoreData['dimensionContents'] ?? [];
        Assert::isArray($dimensionContents, 'Expected dimensionContents to be an array');
        [$allLocales, $productTitle] = $this->persistDimensionContents($product, $dimensionContents);
        Assert::notEmpty($allLocales, 'Expected to find at least one restored locale for the product.');

        // A variant is edited in its parent's variants tab.
        $result = new ProductRestoreResult($parent?->getUuid() ?? $product->getUuid(), $allLocales[0]);

        if ('translation' === $trashItem->getRestoreType()) {
            foreach ($allLocales as $locale) {
                $this->domainEventCollector->collect(new ProductTranslationRestoredEvent(
                    $product,
                    $locale,
                    $restoreData,
                ));
            }

            return $result;
        }

        // Each variant's own event carries its data.
        $payload = $restoreData;
        unset($payload['variants']);
        $this->domainEventCollector->collect(new ProductRestoredEvent(
            $product,
            $productTitle,
            ['locales' => $allLocales],
            $payload,
        ));

        $variants = $restoreData['variants'] ?? [];
        Assert::isArray($variants, 'Expected variants to be an array');
        foreach ($variants as $variantData) {
            Assert::isArray($variantData, 'Expected variantData to be an array');
            $this->restoreVariant($product, $variantData);
        }

        return $result;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(
            null,
            ProductAdmin::EDIT_TABS_VIEW,
            ['id' => 'id', 'locale' => 'locale'],
        );
    }

    /**
     * @param array<mixed> $variantData
     */
    private function restoreVariant(ProductInterface $parent, array $variantData): void
    {
        Assert::string($variantData['uuid'] ?? null, 'Expected variant uuid to be a string');

        $variant = $this->findOrCreateProduct($variantData['uuid']);
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        if (\is_int($variantData['position'] ?? null)) {
            $variant->setPosition($variantData['position']);
        }

        $dimensionContents = $variantData['dimensionContents'] ?? [];
        Assert::isArray($dimensionContents, 'Expected dimensionContents to be an array');
        [$allLocales, $variantTitle] = $this->persistDimensionContents($variant, $dimensionContents);

        $this->domainEventCollector->collect(new ProductRestoredEvent(
            $variant,
            $variantTitle,
            $allLocales ? ['locales' => $allLocales] : [],
            $variantData,
        ));
    }

    private function findOrCreateProduct(string $uuid): ProductInterface
    {
        $product = $this->productRepository->findOneBy(['uuid' => $uuid]);
        if (!$product) {
            $product = $this->productRepository->createNew($uuid);
            $this->productRepository->add($product);
        }

        return $product;
    }

    /**
     * @param array<mixed> $dimensionContents
     *
     * @return array{0: list<string>, 1: string|null} the restored locales and the first title
     */
    private function persistDimensionContents(ProductInterface $product, array $dimensionContents): array
    {
        $allLocales = [];
        $title = null;
        foreach ($dimensionContents as $dimensionContentData) {
            Assert::isArray($dimensionContentData, 'Expected dimensionContentData to be an array');
            /** @var array<string, mixed> $dimensionContentData */
            if (null === $title && \is_string($dimensionContentData['title'] ?? null) && '' !== $dimensionContentData['title']) {
                $title = $dimensionContentData['title'];
            }

            $locale = $dimensionContentData['locale'] ?? null;
            if (\is_string($locale)) {
                $allLocales[] = $locale;
                $this->contentPersister->persist($product, $dimensionContentData, [
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ]);
            }
        }

        return [$allLocales, $title];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>} the normalized draft contents and titles by locale
     */
    private function normalizeDimensionContents(ProductInterface $product, ?string $onlyLocale): array
    {
        /** @var array<string, ProductDimensionContentInterface> $localizedDimensionContents */
        $localizedDimensionContents = [];
        /** @var ProductDimensionContentInterface|null $unlocalizedDimensionContent */
        $unlocalizedDimensionContent = null;
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if (
                DimensionContentInterface::CURRENT_VERSION !== $dimensionContent->getVersion()
                || DimensionContentInterface::STAGE_DRAFT !== $dimensionContent->getStage()
            ) {
                continue;
            }

            if (null === $dimensionContent->getLocale()) {
                $unlocalizedDimensionContent = $dimensionContent;
                continue;
            }

            if (null !== $onlyLocale && $dimensionContent->getLocale() !== $onlyLocale) {
                continue;
            }

            $localizedDimensionContents[$dimensionContent->getLocale()] = $dimensionContent;
        }

        Assert::notNull($unlocalizedDimensionContent, 'Expected to find an unlocalized dimension content for the product.');
        Assert::notEmpty($localizedDimensionContents, 'Expected to find at least one localized dimension content for the product.');

        // Reorder localized dimension contents to match the order defined in availableLocales.
        $availableLocales = $unlocalizedDimensionContent->getAvailableLocales();
        Assert::isArray($availableLocales, 'Expected availableLocales to be an array');
        /** @var array<string, ProductDimensionContentInterface> $localizedDimensionContents */
        $localizedDimensionContents = \array_merge(
            \array_flip(
                \array_filter(
                    $availableLocales, static fn ($locale) => \array_key_exists($locale, $localizedDimensionContents)
                )
            ),
            $localizedDimensionContents,
        );

        $normalized = [];
        $titles = [];
        foreach ($localizedDimensionContents as $locale => $localizedDimensionContent) {
            $mergedDimensionContent = $this->contentMerger->merge(
                new DimensionContentCollection(
                    new ArrayCollection([$unlocalizedDimensionContent, $localizedDimensionContent]),
                    [
                        'locale' => $locale,
                        'stage' => DimensionContentInterface::STAGE_DRAFT,
                        'version' => DimensionContentInterface::CURRENT_VERSION,
                    ],
                    ProductDimensionContent::class,
                ),
            );

            $normalized[] = $this->contentNormalizer->normalize($mergedDimensionContent);

            $title = $localizedDimensionContent->getTitle();
            if ($title) {
                $titles[$locale] = $title;
            }
        }

        return [$normalized, $titles];
    }
}
