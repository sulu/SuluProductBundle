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

namespace Sulu\Product\Infrastructure\Sulu\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentAdditionalWebspace;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\BatchAwareReindexEnhancerInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexProviderEnhancerInterface;

/**
 * One document per published product and per published variant.
 * A variant is shown on its parent's page, so its document carries the parent's slug and webspaces.
 *
 * @phpstan-type CatalogueRow array{
 *     productId: string,
 *     type: string,
 *     parentId: string|null,
 *     dimensionContentId: int,
 *     locale: string,
 *     title: string|null,
 *     code: string|null,
 *     status: string,
 *     productFamilyId: string|null,
 *     productFamilyName: string|null,
 *     mainWebspace: string|null,
 *     slug: string|null,
 *     authored: \DateTimeImmutable|null,
 *     changed: \DateTimeImmutable,
 *     detailsData: array<string, mixed>,
 * }
 * @phpstan-type RawCatalogueRow array{
 *     productId: string,
 *     type: string,
 *     parentId: string|null,
 *     dimensionContentId: int,
 *     locale: string,
 *     title: string|null,
 *     code: string|null,
 *     status: string,
 *     productFamilyId: string|null,
 *     productFamilyName: string|null,
 *     mainWebspace: string|null,
 *     slug: string|null,
 *     authored: \DateTimeImmutable|null,
 *     changed: \DateTimeImmutable,
 *     detailsData: array<string, mixed>,
 *     unlocalizedDetailsData: array<string, mixed>,
 * }
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own ReindexProvider instead
 */
final class CatalogueProductReindexProvider implements ReindexProviderInterface
{
    private const BATCH_SIZE = 100;

    /**
     * @var EntityRepository<ProductDimensionContentInterface>
     */
    private EntityRepository $dimensionContentRepository;

    /**
     * @var EntityRepository<ProductDimensionContentAdditionalWebspace>
     */
    private EntityRepository $additionalWebspacesRepository;

    /**
     * @param iterable<WebsiteProductReindexProviderEnhancerInterface> $enhancers
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly iterable $enhancers = [],
    ) {
        $this->dimensionContentRepository = $entityManager->getRepository(ProductDimensionContentInterface::class);
        $this->additionalWebspacesRepository = $entityManager->getRepository(ProductDimensionContentAdditionalWebspace::class);
    }

    public function total(): ?int
    {
        return null;
    }

    public function provide(ReindexConfig $reindexConfig): \Generator
    {
        $identifiers = $reindexConfig->getIdentifiers();
        $offset = 0;
        $batch = $this->loadBatch($identifiers, $offset);

        while ([] !== $batch) {
            $webspaces = $this->loadWebspaces($batch);
            $parentSlugs = $this->loadParentSlugs($batch);

            foreach ($this->enhancers as $enhancer) {
                if ($enhancer instanceof BatchAwareReindexEnhancerInterface) {
                    $enhancer->prepareBatch($batch);
                }
            }

            foreach ($batch as $row) {
                $data = $this->createDocument($row, $webspaces, $parentSlugs);

                foreach ($this->enhancers as $enhancer) {
                    $data = $enhancer->enhanceDocument($row, $data);
                }

                if ('' === $data['title']) {
                    $data['title'] = (string) $row['title'];
                }

                yield $data;
            }

            $offset += self::BATCH_SIZE;
            $batch = $this->loadBatch($identifiers, $offset);
        }
    }

    /**
     * @param CatalogueRow $row
     * @param array<string, string[]> $webspaces keyed by "<productId>__<locale>"
     * @param array<string, string> $parentSlugs keyed by "<parentId>__<locale>"
     *
     * @return array<string, mixed>
     */
    private function createDocument(array $row, array $webspaces, array $parentSlugs): array
    {
        $key = $row['productId'] . '__' . $row['locale'];
        $isVariant = ProductInterface::TYPE_VARIANT === $row['type'];
        $webspaceKey = $isVariant && null !== $row['parentId'] ? $row['parentId'] . '__' . $row['locale'] : $key;
        $url = $isVariant && null !== $row['parentId']
            ? ($parentSlugs[$row['parentId'] . '__' . $row['locale']] ?? '')
            : (string) $row['slug'];

        // A variant keeps its own image; an empty one is not filled from the parent.
        $image = $row['detailsData']['image'] ?? null;
        $mediaId = \is_array($image) && isset($image['id']) && \is_numeric($image['id']) ? (string) $image['id'] : '';

        return [
            'id' => ProductIndex::documentId($row['productId'], $row['locale']),
            'resourceId' => $row['productId'],
            'type' => $row['type'],
            'parentId' => (string) $row['parentId'],
            'code' => (string) $row['code'],
            'locale' => $row['locale'],
            'webspaces' => $webspaces[$webspaceKey] ?? [],
            'title' => '',
            'url' => $url,
            'content' => [],
            'mediaId' => $mediaId,
            'productFamilyId' => (string) $row['productFamilyId'],
            'productFamilyName' => (string) $row['productFamilyName'],
            'status' => $row['status'],
            'authoredAt' => ($row['authored'] ?? $row['changed'])->format('c'),
            'changedAt' => $row['changed']->format('c'),
            'attributes' => [],
            'metadata' => [],
        ];
    }

    /**
     * @param string[] $identifiers
     *
     * @return array<int, CatalogueRow>
     */
    private function loadBatch(array $identifiers, int $offset): array
    {
        // Code, status and the product family are stored once per product, on the unlocalized dimension content.
        $queryBuilder = $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->innerJoin(
                $this->dimensionContentRepository->getClassName(),
                'unlocalizedDimensionContent',
                'WITH',
                'unlocalizedDimensionContent.product = product'
                . ' AND unlocalizedDimensionContent.locale IS NULL'
                . ' AND unlocalizedDimensionContent.stage = dimensionContent.stage'
                . ' AND unlocalizedDimensionContent.version = dimensionContent.version',
            )
            ->leftJoin('dimensionContent.route', 'route')
            ->leftJoin('unlocalizedDimensionContent.productFamily', 'productFamily')
            ->leftJoin('productFamily.translations', 'productFamilyTranslation', 'WITH', 'productFamilyTranslation.locale = dimensionContent.locale')
            ->select('product.uuid AS productId')
            ->addSelect('product.type AS type')
            ->addSelect('IDENTITY(product.parent) AS parentId')
            ->addSelect('dimensionContent.id AS dimensionContentId')
            ->addSelect('dimensionContent.locale')
            ->addSelect('dimensionContent.title')
            ->addSelect('unlocalizedDimensionContent.code')
            ->addSelect('unlocalizedDimensionContent.status')
            ->addSelect('productFamily.uuid AS productFamilyId')
            ->addSelect('productFamilyTranslation.name AS productFamilyName')
            ->addSelect('dimensionContent.mainWebspace')
            ->addSelect('route.slug')
            ->addSelect('dimensionContent.authored')
            ->addSelect('dimensionContent.changed')
            ->addSelect('dimensionContent.detailsData')
            ->addSelect('unlocalizedDimensionContent.detailsData AS unlocalizedDetailsData')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale IS NOT NULL')
            ->andWhere('dimensionContent.version = :version');

        $parameters = [
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        if (0 < \count($identifiers)) {
            $conditions = [];

            foreach ($identifiers as $index => $identifier) {
                $parts = \explode('__', $identifier);
                if (ProductInterface::RESOURCE_KEY !== $parts[0]) {
                    continue;
                }

                $conditions[] = "(product.uuid = :id{$index} AND dimensionContent.locale = :locale{$index})";
                $parameters["id{$index}"] = $parts[1] ?? '';
                $parameters["locale{$index}"] = $parts[2] ?? '';
            }

            if (!$conditions) {
                return [];
            }

            $queryBuilder->andWhere(\implode(' OR ', $conditions));
        }

        foreach ($parameters as $parameterKey => $parameterValue) {
            $queryBuilder->setParameter($parameterKey, $parameterValue);
        }

        foreach ($this->enhancers as $enhancer) {
            $enhancer->enhanceQuery($queryBuilder);
        }

        $queryBuilder->orderBy('dimensionContent.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults(self::BATCH_SIZE);

        /** @var array<int, RawCatalogueRow> $rows */
        $rows = $queryBuilder->getQuery()->getResult();

        return $this->mergeDetailsData($rows);
    }

    /**
     * Details are split over both dimension contents by multilinguality, so a row carries the
     * merged bag, localized winning, the same precedence ProductDetailsMerger applies.
     *
     * @param array<int, RawCatalogueRow> $rows
     *
     * @return array<int, CatalogueRow>
     */
    private function mergeDetailsData(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $unlocalizedDetailsData = $row['unlocalizedDetailsData'];
            unset($row['unlocalizedDetailsData']);
            $row['detailsData'] = \array_merge($unlocalizedDetailsData, $row['detailsData']);
            $merged[] = $row;
        }

        return $merged;
    }

    /**
     * Main plus additional webspaces per document key "<productId>__<locale>".
     *
     * @param array<int, CatalogueRow> $batch
     *
     * @return array<string, string[]>
     */
    private function loadWebspaces(array $batch): array
    {
        $webspaces = [];
        $byDimensionContentId = [];
        foreach ($batch as $row) {
            $key = $row['productId'] . '__' . $row['locale'];
            $webspaces[$key] = $row['mainWebspace'] ? [$row['mainWebspace']] : [];
            $byDimensionContentId[$row['dimensionContentId']] = $key;
        }

        // Variants inherit the parent's webspaces; parents outside the batch are loaded with them.
        foreach ($this->loadParentWebspaceRows($this->parentIds($batch)) as $parentRow) {
            $key = $parentRow['productId'] . '__' . $parentRow['locale'];
            $webspaces[$key] ??= $parentRow['mainWebspace'] ? [$parentRow['mainWebspace']] : [];
            $byDimensionContentId[$parentRow['dimensionContentId']] = $key;
        }

        if ([] === $byDimensionContentId) {
            return $webspaces;
        }

        foreach ($this->loadAdditionalWebspaceRows(\array_keys($byDimensionContentId)) as $additionalRow) {
            $key = $byDimensionContentId[$additionalRow['dimensionContentId']] ?? null;
            if (null !== $key && !\in_array($additionalRow['webspace'], $webspaces[$key], true)) {
                $webspaces[$key][] = $additionalRow['webspace'];
            }
        }

        return $webspaces;
    }

    /**
     * @param string[] $parentIds
     *
     * @return array<int, array{productId: string, locale: string, mainWebspace: string|null, dimensionContentId: int}>
     */
    private function loadParentWebspaceRows(array $parentIds): array
    {
        if ([] === $parentIds) {
            return [];
        }

        /** @var array<int, array{productId: string, locale: string, mainWebspace: string|null, dimensionContentId: int}> */
        return $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->select('product.uuid AS productId', 'dimensionContent.locale', 'dimensionContent.mainWebspace', 'dimensionContent.id AS dimensionContentId')
            ->where('product.uuid IN (:parentIds)')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version = :version')
            ->andWhere('dimensionContent.locale IS NOT NULL')
            ->setParameter('parentIds', $parentIds)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->getQuery()->getResult();
    }

    /**
     * @param int[] $dimensionContentIds
     *
     * @return array<int, array{dimensionContentId: int, webspace: string}>
     */
    private function loadAdditionalWebspaceRows(array $dimensionContentIds): array
    {
        return $this->additionalWebspacesRepository->createQueryBuilder('additionalWebspace')
            ->select('IDENTITY(additionalWebspace.productDimensionContent) AS dimensionContentId')
            ->addSelect('additionalWebspace.additionalWebspace AS webspace')
            ->where('additionalWebspace.productDimensionContent IN (:dimensionContentIds)')
            ->setParameter('dimensionContentIds', $dimensionContentIds)
            ->getQuery()->getResult();
    }

    /**
     * Live route slugs of the batch's parents, keyed by "<parentId>__<locale>".
     *
     * @param array<int, CatalogueRow> $batch
     *
     * @return array<string, string>
     */
    private function loadParentSlugs(array $batch): array
    {
        $slugs = [];
        foreach ($this->loadParentSlugRows($this->parentIds($batch)) as $row) {
            $slugs[$row['productId'] . '__' . $row['locale']] = $row['slug'];
        }

        return $slugs;
    }

    /**
     * @param string[] $parentIds
     *
     * @return array<int, array{productId: string, locale: string, slug: string}>
     */
    private function loadParentSlugRows(array $parentIds): array
    {
        if ([] === $parentIds) {
            return [];
        }

        /** @var array<int, array{productId: string, locale: string, slug: string}> */
        return $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->innerJoin('dimensionContent.route', 'route')
            ->select('product.uuid AS productId', 'dimensionContent.locale', 'route.slug')
            ->where('product.uuid IN (:parentIds)')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version = :version')
            ->andWhere('dimensionContent.locale IS NOT NULL')
            ->setParameter('parentIds', $parentIds)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->getQuery()->getResult();
    }

    /**
     * @param array<int, CatalogueRow> $batch
     *
     * @return string[]
     */
    private function parentIds(array $batch): array
    {
        return \array_values(\array_unique(\array_filter(\array_column($batch, 'parentId'))));
    }

    public static function getIndex(): string
    {
        return ProductIndex::NAME;
    }
}
