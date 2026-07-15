<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Infrastructure\Sulu\Reference;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollector;
use Sulu\Bundle\ReferenceBundle\Application\Refresh\ReferenceRefresherInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentResolver\ContentViewResolver\ContentViewResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * @internal Modifying or depending on this service may result in unexpected behavior and is not supported.
 *
 * To customize the behavior of this class, override the service by providing your own class that implements
 * ReferenceRefresherInterface, and register it using the same resource key.
 */
class ProductReferenceRefresher implements ReferenceRefresherInterface
{
    /**
     * @var EntityRepository<ProductDimensionContentInterface>
     */
    private EntityRepository $productDimensionContentRepository;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReferenceRepositoryInterface $referenceRepository,
        private ContentViewResolverInterface $contentViewResolver,
        private ContentMergerInterface $contentMerger,
    ) {
        /** @var EntityRepository<ProductDimensionContentInterface> $repository */
        $repository = $this->entityManager->getRepository(ProductDimensionContentInterface::class);
        $this->productDimensionContentRepository = $repository;
    }

    public static function getResourceKey(): string
    {
        return ProductInterface::RESOURCE_KEY;
    }

    public function refresh(?array $filter = null): \Generator
    {
        $productDimensionContentsGenerator = $this->getProductDimensionContentsGenerator($filter);

        $currentResourceId = null;
        $currentGroup = [];
        /** @var ProductDimensionContentInterface $dimensionContent */
        foreach ($productDimensionContentsGenerator as $dimensionContent) {
            $resourceId = $dimensionContent->getResource()->getId();

            if (null === $currentResourceId) {
                $currentResourceId = $resourceId;
            }

            if ($resourceId !== $currentResourceId) {
                // Process finished group
                foreach ($this->resolveProductDimensionContents($currentGroup) as $merged) {
                    $this->processProductDimensionContent($merged);
                    yield $merged;
                }

                // Reset for next group
                $currentGroup = [];
                $currentResourceId = $resourceId;
            }

            $currentGroup[] = $dimensionContent;
        }

        // Process the last group if present
        if ([] !== $currentGroup) {
            foreach ($this->resolveProductDimensionContents($currentGroup) as $merged) {
                $this->processProductDimensionContent($merged);
                yield $merged;
            }
        }
    }

    /**
     * Process a single product dimension content: collect and persist references.
     */
    private function processProductDimensionContent(ProductDimensionContentInterface $productDimensionContent): void
    {
        $referenceCollector = new ReferenceCollector(
            referenceRepository: $this->referenceRepository,
            referenceResourceKey: $productDimensionContent->getResourceKey(),
            referenceResourceId: (string) $productDimensionContent->getResourceId(),
            referenceLocale: $productDimensionContent->getLocale() ?? '',
            referenceTitle: $productDimensionContent->getTitle() ?? '',
            referenceContext: $productDimensionContent->getStage(),
            referenceRouterAttributes: [
                'locale' => $productDimensionContent->getLocale() ?? '',
            ]
        );

        $contentViews = $this->contentViewResolver->getContentViews(dimensionContent: $productDimensionContent);

        foreach ($contentViews as $key => $contentView) {
            $basePath = 'template' !== $key ? (string) $key : '';
            $references = $contentView->getAllReferencesRecursively($basePath);

            foreach ($references as $reference) {
                $referenceCollector->addReference(
                    $reference->getResourceKey(),
                    (string) $reference->getResourceId(),
                    $reference->getPath()
                );
            }
        }

        $image = $productDimensionContent->getImage();
        if (null !== $image) {
            $referenceCollector->addReference(
                MediaInterface::RESOURCE_KEY,
                (string) $image,
                'image',
            );
        }

        foreach ($productDimensionContent->getDocuments() ?? [] as $index => $mediaId) {
            $referenceCollector->addReference(
                MediaInterface::RESOURCE_KEY,
                (string) $mediaId,
                'documents/' . $index,
            );
        }

        $referenceCollector->persistReferences();
    }

    /**
     * @param array{
     *      resourceId: string,
     *      resourceKey: string,
     *      locale: string,
     *      stage: string
     *  }|null $filter
     *
     * @return iterable<ProductDimensionContentInterface>
     */
    private function getProductDimensionContentsGenerator(?array $filter = null): iterable
    {
        $queryBuilder = $this->productDimensionContentRepository->createQueryBuilder('dimensionContent')
            ->where('dimensionContent.version = :version')
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            // Order by resourceId to keep groups intact
            ->orderBy('dimensionContent.product', 'ASC');

        if (null !== $filter) {
            $queryBuilder
                ->join(
                    'dimensionContent.product',
                    'product',
                    Join::WITH,
                    'product.uuid = :resourceId'
                )
                ->andWhere('dimensionContent.locale = :locale OR dimensionContent.locale IS NULL')
                ->andWhere('dimensionContent.stage = :stage')
                ->setParameter('resourceId', $filter['resourceId'])
                ->setParameter('locale', $filter['locale'])
                ->setParameter('stage', $filter['stage']);
        }

        /** @var iterable<ProductDimensionContentInterface> $result */
        $result = $queryBuilder->getQuery()->toIterable();

        return $result;
    }

    /**
     * @param iterable<ProductDimensionContentInterface> $productDimensionContents
     *
     * @return \Generator<ProductDimensionContentInterface>
     */
    private function resolveProductDimensionContents(iterable $productDimensionContents): \Generator
    {
        $groupedProductDimensionContents = [];
        /** @var ProductDimensionContentInterface $productDimensionContent */
        foreach ($productDimensionContents as $productDimensionContent) {
            $locale = $productDimensionContent->getLocale() ?? '';
            $groupedProductDimensionContents[$productDimensionContent->getResource()->getId()][$productDimensionContent->getStage()][$locale] = $productDimensionContent;
        }

        foreach ($groupedProductDimensionContents as $productDimensionContentByStage) {
            foreach ($productDimensionContentByStage as $stage => $productDimensionContentByLocale) {
                $unlocalizedDimensionContent = $productDimensionContentByLocale[''] ?? null;
                /** @var ProductDimensionContentInterface $productDimensionContent */
                foreach ($productDimensionContentByLocale as $locale => $productDimensionContent) {
                    if ('' === $locale) {
                        continue;
                    }
                    yield $this->contentMerger->merge(
                        new DimensionContentCollection(
                            new ArrayCollection($unlocalizedDimensionContent ? [$productDimensionContent, $unlocalizedDimensionContent] : [$productDimensionContent]),
                            $productDimensionContent::getEffectiveDimensionAttributes(['locale' => $locale, 'stage' => $stage]),
                            ProductDimensionContent::class
                        )
                    );
                }
                $unlocalizedDimensionContent = null;
            }
        }
    }
}
