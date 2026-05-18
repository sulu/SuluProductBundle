<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Infrastructure\Sulu\Content;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;
use Sulu\Bundle\AdminBundle\Metadata\GroupProviderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\Builder;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\BuilderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\ProviderConfigurationInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentProviderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;

/**
 * @phpstan-type ProductSmartContentFilters array{
 *       categories: int[],
 *       categoryOperator: 'AND'|'OR',
 *       websiteCategories: string[],
 *       websiteCategoryOperator: 'AND'|'OR',
 *       tags: int[],
 *       tagOperator: 'AND'|'OR',
 *       websiteTags: string[],
 *       websiteTagOperator: 'AND'|'OR',
 *       types: string[],
 *       typesOperator: 'OR',
 *       templateKeys?: string[],
 *       locale: string,
 *       dataSource: string|null,
 *       limit: int|null,
 *       offset: int,
 *       includeSubFolders: bool,
 *       excludeDuplicates: bool,
 *       audienceTargeting?: bool,
 *       audienceTargeting?: bool,
 *       targetGroupId?: int,
 *       segmentKey?: string,
 *       webspaceKey?: string,
 *   }
 * @phpstan-type ProductSmartContentCountFilters array{
 *        categories: int[],
 *        categoryOperator: 'AND'|'OR',
 *        websiteCategories: string[],
 *        websiteCategoryOperator: 'AND'|'OR',
 *        tags: int[],
 *        tagOperator: 'AND'|'OR',
 *        websiteTags: string[],
 *        websiteTagOperator: 'AND'|'OR',
 *        types: string[],
 *        typesOperator: 'OR',
 *        templateKeys?: string[],
 *        locale: string,
 *        dataSource: string|null,
 *        limit: int|null,
 *        includeSubFolders: bool,
 *        excludeDuplicates: bool,
 *        audienceTargeting?: bool,
 *        audienceTargeting?: bool,
 *        targetGroupId?: int,
 *        segmentKey?: string,
 *        webspaceKey?: string,
 *    }
 */
readonly class ProductSmartContentProvider implements SmartContentProviderInterface
{
    /**
     * @var EntityRepository<ProductInterface>
     */
    private EntityRepository $entityRepository;

    /**
     * @var EntityRepository<ProductDimensionContentInterface>
     */
    private EntityRepository $entityDimensionContentRepository;

    /**
     * @var class-string<ProductDimensionContentInterface>
     */
    private string $productDimensionContentClassName;

    public function __construct(
        private DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
        private SmartContentQueryEnhancer $smartContentQueryEnhancer,
        EntityManagerInterface $entityManager,
        private GroupProviderInterface $groupProvider,
    ) {
        $this->entityRepository = $entityManager->getRepository(ProductInterface::class);
        $this->entityDimensionContentRepository = $entityManager->getRepository(ProductDimensionContentInterface::class);
        $this->productDimensionContentClassName = $this->entityDimensionContentRepository->getClassName();
    }

    public function getConfiguration(): ProviderConfigurationInterface
    {
        return $this->getConfigurationBuilder()->getConfiguration();
    }

    protected function getConfigurationBuilder(): BuilderInterface
    {
        $builder = Builder::create()
            ->enableTags()
            ->enableCategories()
            ->enableLimit()
            ->enablePagination()
            ->enablePresentAs()
            ->enableSorting(
                [
                    ['column' => 'published', 'title' => 'sulu_admin.published'],
                    ['column' => 'authored', 'title' => 'sulu_admin.authored'],
                    ['column' => 'created', 'title' => 'sulu_admin.created'],
                    ['column' => 'changed', 'title' => 'sulu_admin.changed'],
                    ['column' => 'title', 'title' => 'sulu_admin.title'],
                ],
            )
            ->enableTypes(\array_values(\array_map(
                function($group) {
                    return [
                        'title' => $group->title,
                        'type' => $group->identifier,
                    ];
                },
                $this->groupProvider->getGroups(),
            )))
            ->enableProperties([
                'title' => 'title',
                'url' => 'url',
            ]);

        // TODO
        //        if ($this->hasAudienceTargeting) {
        //            $builder->enableAudienceTargeting();
        //        }

        return $builder;
    }

    /**
     * @param ProductSmartContentCountFilters $filters
     */
    public function countBy(array $filters, array $params = []): int
    {
        /** @var ProductSmartContentCountFilters $filters */
        $filters = $this->enhanceWithDimensionAttributes($filters);

        $alias = 'product';
        $queryBuilder = $this->entityRepository->createQueryBuilder($alias);

        $filters = $this->mapFilters($filters, $params);
        $this->dimensionContentQueryEnhancer->addFilters(
            $queryBuilder,
            $alias,
            $this->productDimensionContentClassName,
            $filters,
            [],
        );
        $this->addInternalFilters($queryBuilder, $filters, $alias);

        $queryBuilder->select('COUNT(DISTINCT product.uuid)');

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @param ProductSmartContentFilters $filters
     * @param array{
     *     title?: 'asc'|'desc',
     *     authored?: 'asc'|'desc',
     *     published?: 'asc'|'desc',
     *     created?: 'asc'|'desc',
     *     changed?: 'asc'|'desc',
     * } $sortBys
     *
     * @return array<array{id: string, title: string}>
     */
    public function findFlatBy(array $filters, array $sortBys, array $params = []): array
    {
        /** @var ProductSmartContentFilters $filters */
        $filters = $this->enhanceWithDimensionAttributes($filters);

        $sortBys = $this->mapSortBys($sortBys);

        $alias = 'product';
        $queryBuilder = $this->entityRepository->createQueryBuilder($alias);

        $filters = $this->mapFilters($filters, $params);
        $this->dimensionContentQueryEnhancer->addFilters(
            $queryBuilder,
            $alias,
            $this->productDimensionContentClassName,
            $filters,
            $sortBys,
        );
        $this->addInternalFilters($queryBuilder, $filters, $alias);

        // TODO refactor this part to not use distinct
        // we need the distinct here, because joins due to tags/categories can lead to duplicate results
        $queryBuilder->select('DISTINCT ' . $alias . '.uuid as id');
        $queryBuilder->addSelect('filterDimensionContent.title');
        $this->smartContentQueryEnhancer->addOrderBySelects($queryBuilder);
        $this->smartContentQueryEnhancer->addPagination($queryBuilder, $filters['offset'] ?? 0, $filters['limit']);

        /** @var array{id: string, title: string}[] $result */
        $result = $queryBuilder->getQuery()->getArrayResult();

        return $result;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    protected function enhanceWithDimensionAttributes(array $filters): array
    {
        $dimensionAttributes = [
            // we always use the live stage
            'stage' => $filters['stage'] ?? DimensionContentInterface::STAGE_LIVE,
        ];

        return \array_merge($dimensionAttributes, $filters);
    }

    /**
     * @param ProductSmartContentFilters|ProductSmartContentCountFilters $filters
     * @param array<string, mixed> $params
     *
     * @return array{
     *         categoryIds?: int[],
     *         categoryOperator: 'AND'|'OR',
     *         websiteCategories: string[],
     *         websiteCategoryOperator: 'AND'|'OR',
     *         tagNames?: string[],
     *         tagOperator: 'AND'|'OR',
     *         websiteTags: string[],
     *         websiteTagOperator: 'AND'|'OR',
     *         templateKeys?: string[],
     *         typesOperator: 'OR',
     *         locale: string,
     *         dataSource: string|null,
     *         limit: int|null,
     *         offset?: int,
     *         includeSubFolders: bool,
     *         excludeDuplicates: bool,
     *         audienceTargeting?: bool,
     *         webspaceKey?: string
     *     }
     */
    protected function mapFilters(array $filters, array $params = []): array
    {
        $filters['templateKeys'] = $this->resolveTemplateKeys(
            $filters['templateKeys'] ?? [],
            $filters['types'],
            $params,
        );
        unset($filters['types']);

        if ($filters['categories']) {
            $filters['categoryIds'] = $filters['categories'];
            unset($filters['categories']);
        }

        if ($filters['tags']) {
            $filters['tagIds'] = $filters['tags'];
            unset($filters['tags']);
        }

        return $filters;
    }

    /**
     * @param array<string> $existingTemplateKeys
     * @param array<string> $filterGroupIdentifiers
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    private function resolveTemplateKeys(array $existingTemplateKeys, array $filterGroupIdentifiers, array $params): array
    {
        $groupIdentifiers = $filterGroupIdentifiers;
        if ([] === $groupIdentifiers) {
            $groupsParam = $params['groups'] ?? null;
            if (\is_string($groupsParam)) {
                $groupIdentifiers = \array_values(\array_filter(\array_map('trim', \explode(',', $groupsParam))));
            }
        }

        $templateKeys = \array_values($existingTemplateKeys);
        if ([] !== $groupIdentifiers) {
            $templatesFromGroups = $this->expandGroupsToTemplates($groupIdentifiers);
            $templateKeys = [] !== $templateKeys
                ? \array_values(\array_intersect($templateKeys, $templatesFromGroups))
                : $templatesFromGroups;
        }

        $templateParam = $params['templateKeys'] ?? null;
        if (\is_string($templateParam)) {
            $templateKeysParam = \array_values(\array_filter(\array_map('trim', \explode(',', $templateParam))));
            if ([] !== $templateKeysParam) {
                $templateKeys = [] !== $templateKeys
                    ? \array_values(\array_intersect($templateKeys, $templateKeysParam))
                    : $templateKeysParam;
            }
        }

        return $templateKeys;
    }

    /**
     * @param array<string> $identifiers
     *
     * @return list<string>
     */
    private function expandGroupsToTemplates(array $identifiers): array
    {
        $templates = [];
        foreach ($this->groupProvider->getGroups() as $group) {
            if (\in_array($group->identifier, $identifiers, true)) {
                $templates = \array_merge($templates, \array_filter($group->templates, 'is_string'));
            }
        }

        return \array_values(\array_unique($templates));
    }

    /**
     * @param array{
     *     title?: 'asc'|'desc',
     *     published?: 'asc'|'desc',
     *     created?: 'asc'|'desc',
     *     changed?: 'asc'|'desc',
     * } $sortBys
     *
     * @return array{
     *     title?: 'asc'|'desc',
     *     workflowPublished?: 'asc'|'desc',
     *     created?: 'asc'|'desc',
     *     changed?: 'asc'|'desc',
     * }
     */
    protected function mapSortBys(array $sortBys): array
    {
        if (\array_key_exists('published', $sortBys)) {
            $sortBys['workflowPublished'] = $sortBys['published'];
            unset($sortBys['published']);
        }

        return $sortBys;
    }

    /**
     * @param array{
     *     websiteCategories: string[],
     *     websiteCategoryOperator: 'AND'|'OR',
     *     websiteTags: string[],
     *     websiteTagOperator: 'AND'|'OR',
     *     webspaceKey?: string,
     *  } $filters
     */
    protected function addInternalFilters(QueryBuilder $queryBuilder, array $filters, string $alias): void
    {
        $websiteCategoryIds = $filters['websiteCategories'];
        if ([] !== $websiteCategoryIds) {
            $this->smartContentQueryEnhancer->addJoinFilter(
                $queryBuilder,
                'filterDimensionContent.excerptCategories',
                'websiteFilterCategoryId',
                'id',
                'websiteCategoryIds',
                $websiteCategoryIds,
                $filters['websiteCategoryOperator'],
            );
        }

        $websiteTagNames = $filters['websiteTags'];
        if ([] !== $websiteTagNames) {
            $this->smartContentQueryEnhancer->addJoinFilter(
                $queryBuilder,
                'filterDimensionContent.excerptTags',
                'websiteFilterTagName',
                'name',
                'websiteTagNames',
                $websiteTagNames,
                $filters['websiteTagOperator'],
            );
        }

        $webspaceKey = $filters['webspaceKey'] ?? null;
        if (null !== $webspaceKey) {
            $queryBuilder->leftJoin('filterDimensionContent.additionalWebspaces', 'additionalWebspace');
            $queryBuilder->andWhere('filterDimensionContent.mainWebspace = :webspaceKey OR additionalWebspace.additionalWebspace = :webspaceKey');
            $queryBuilder->setParameter('webspaceKey', $webspaceKey);
        }
    }

    public function getType(): string
    {
        return ProductInterface::RESOURCE_KEY;
    }

    public function getResourceLoaderKey(): string
    {
        return ProductResourceLoader::RESOURCE_LOADER_KEY;
    }
}
