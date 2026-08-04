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

        $data = [
            'dimensionContents' => [],
        ];

        $restoreType = $options['locale'] ?? null ? 'translation' : null;

        $titles = [];
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

            if ('translation' === $restoreType && $dimensionContent->getLocale() !== $options['locale']) {
                continue;
            }

            $localizedDimensionContents[$dimensionContent->getLocale()] = $dimensionContent;
        }

        Assert::notNull($unlocalizedDimensionContent, 'Expected to find an unlocalized dimension content for the product.');
        Assert::notEmpty($localizedDimensionContents, 'Expected to find at least one localized dimension content for the product.');

        $data['type'] = $product->getType();
        $data['parent'] = $product->getParent()?->getUuid();

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

            $normalizedContent = $this->contentNormalizer->normalize($mergedDimensionContent);
            $data['dimensionContents'][] = $normalizedContent;

            $title = $localizedDimensionContent->getTitle();

            if ($title) {
                $titles[$locale] = $title;
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

        $product = $this->productRepository->findOneBy(['uuid' => $productUuid]);
        if (!$product) {
            $product = $this->productRepository->createNew($productUuid);
            $this->productRepository->add($product);
        }

        $parentUuid = $restoreData['parent'] ?? null;
        $product->setParent(
            \is_string($parentUuid) ? $this->productRepository->findOneBy(['uuid' => $parentUuid]) : null,
        );

        if (\is_string($restoreData['type'] ?? null)) {
            $product->setType($restoreData['type']);
        }

        $dimensionContents = $restoreData['dimensionContents'] ?? [];
        /** @var list<string> $allLocales */
        $allLocales = [];
        /** @var string|null $productTitle */
        $productTitle = null;
        Assert::isArray($dimensionContents, 'Expected dimensionContents to be an array');
        foreach ($dimensionContents as $dimensionContentData) {
            Assert::isArray($dimensionContentData, 'Expected dimensionContentData to be an array');
            /** @var array<string, mixed> $dimensionContentData */
            if (null === $productTitle && \array_key_exists('title', $dimensionContentData) && $dimensionContentData['title']) {
                Assert::string($dimensionContentData['title']);
                $productTitle = $dimensionContentData['title'];
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

        $context = $allLocales ? ['locales' => $allLocales] : [];

        if ('translation' === $trashItem->getRestoreType()) {
            foreach ($allLocales as $locale) {
                $this->domainEventCollector->collect(new ProductTranslationRestoredEvent(
                    $product,
                    $locale,
                    $restoreData,
                ));
            }

            return $product;
        }

        $this->domainEventCollector->collect(new ProductRestoredEvent(
            $product,
            $productTitle,
            $context,
            $restoreData,
        ));

        return $product;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(
            null,
            ProductAdmin::EDIT_TABS_VIEW,
            ['id' => 'id'],
            null, // TODO serialization group?
        );
    }
}
