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

namespace Sulu\Product\Infrastructure\Sulu\Content;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sulu\Bundle\AdminBundle\SmartContent\Configuration\BuilderInterface;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Model\Route;

readonly class PageTreeProductSmartContentProvider extends ProductSmartContentProvider
{
    public const PROVIDER_TYPE = 'products_page_tree';

    public function __construct(
        DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
        SmartContentQueryEnhancer $smartContentQueryEnhancer,
        EntityManagerInterface $entityManager,
    ) {
        parent::__construct(
            $dimensionContentQueryEnhancer,
            $smartContentQueryEnhancer,
            $entityManager,
        );
    }

    protected function getConfigurationBuilder(): BuilderInterface
    {
        return parent::getConfigurationBuilder()
            ->enableDatasource(
                PageInterface::RESOURCE_KEY,
                PageInterface::RESOURCE_KEY,
                'column_list'
            );
    }

    /**
     * @param array{
     *     websiteCategories: string[],
     *     websiteCategoryOperator: 'AND'|'OR',
     *     websiteTags: string[],
     *     websiteTagOperator: 'AND'|'OR',
     *     dataSource?: string|null,
     *     locale?: string|null,
     *     includeSubFolders?: bool,
     * } $filters
     */
    protected function addInternalFilters(QueryBuilder $queryBuilder, array $filters, string $alias): void
    {
        parent::addInternalFilters($queryBuilder, $filters, $alias);

        $dataSource = $filters['dataSource'] ?? null;
        if (null === $dataSource || '' === $dataSource) {
            return;
        }

        $locale = $filters['locale'] ?? null;
        $includeSubFolders = $filters['includeSubFolders'] ?? false;

        $queryBuilder->join(
            Route::class,
            'productRoute',
            'WITH',
            'productRoute.resourceKey = :productResourceKey
             AND productRoute.resourceId = ' . $alias . '.uuid
             AND productRoute.locale = :routeLocale'
        );
        $queryBuilder->setParameter('productResourceKey', ProductInterface::RESOURCE_KEY);
        $queryBuilder->setParameter('routeLocale', $locale);

        $queryBuilder->join('productRoute.parentRoute', 'parentRoute');

        if (!$includeSubFolders) {
            $queryBuilder->andWhere('parentRoute.resourceId = :dataSource');
            $queryBuilder->setParameter('dataSource', $dataSource);
        } else {
            $queryBuilder->join(
                PageInterface::class,
                'dataSourcePage',
                'WITH',
                'dataSourcePage.uuid = :dataSource'
            );
            $queryBuilder->join(
                PageInterface::class,
                'targetPage',
                'WITH',
                'targetPage.uuid = parentRoute.resourceId'
            );
            $queryBuilder->andWhere('targetPage.lft BETWEEN dataSourcePage.lft AND dataSourcePage.rgt');
            $queryBuilder->setParameter('dataSource', $dataSource);
        }
    }

    public function getType(): string
    {
        return self::PROVIDER_TYPE;
    }
}
