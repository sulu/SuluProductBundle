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
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\AdminProductReindexProviderEnhancerInterface;

/**
 * @phpstan-type Product array{
 *     productId: int,
 *     changed: \DateTimeImmutable,
 *     created: \DateTimeImmutable,
 *     title: string,
 *     locale: string,
 *     templateKey: string,
 * }
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *            use Symfony Dependency Injection to override or create your own ReindexProvider instead
 */
final class AdminProductReindexProvider implements ReindexProviderInterface
{
    /**
     * @var EntityRepository<ProductDimensionContentInterface>
     */
    private EntityRepository $dimensionContentRepository;

    /**
     * @param iterable<AdminProductReindexProviderEnhancerInterface> $enhancers
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly iterable $enhancers = [],
    ) {
        $this->dimensionContentRepository = $entityManager->getRepository(ProductDimensionContentInterface::class);
    }

    public function total(): ?int
    {
        // Todo: Add correct count for multiple locales.
        return null;
    }

    public function provide(ReindexConfig $reindexConfig): \Generator
    {
        $products = $this->loadProducts($reindexConfig->getIdentifiers());

        /** @var Product $product */
        foreach ($products as $product) {
            $data = [
                'id' => ProductInterface::RESOURCE_KEY . '__' . ((string) $product['productId']) . '__' . $product['locale'],
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => (string) $product['productId'],
                'changedAt' => $product['changed']->format('c'),
                'createdAt' => $product['created']->format('c'),
                'title' => $product['title'],
                'locale' => $product['locale'],
                'securityContext' => ProductAdmin::SECURITY_CONTEXT,
            ];

            foreach ($this->enhancers as $enhancer) {
                $data = $enhancer->enhanceDocument($product, $data);
            }

            yield $data;
        }
    }

    /**
     * @param string[] $identifiers
     *
     * @return iterable<Product>
     */
    private function loadProducts(array $identifiers = []): iterable
    {
        $qb = $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->select('IDENTITY(dimensionContent.product) AS productId')
            ->addSelect('dimensionContent.created')
            ->addSelect('dimensionContent.changed')
            ->addSelect('dimensionContent.title')
            ->addSelect('dimensionContent.locale')
            ->addSelect('dimensionContent.templateKey')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale IS NOT NULL')
            ->andWhere('dimensionContent.version = :version');

        $parameters = [
            'stage' => DimensionContentInterface::STAGE_DRAFT,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        if (0 < \count($identifiers)) {
            $conditions = [];

            foreach ($identifiers as $index => $identifier) {
                $resourceKey = \explode('__', $identifier)[0];

                if (ProductInterface::RESOURCE_KEY !== $resourceKey) {
                    continue;
                }

                $id = \explode('__', $identifier)[1] ?? '';
                $locale = \explode('__', $identifier)[2] ?? '';

                $conditions[] = "(dimensionContent.product = :id{$index} AND dimensionContent.locale = :locale{$index})";
                $parameters["id{$index}"] = $id;
                $parameters["locale{$index}"] = $locale;
            }

            if (!$conditions) {
                return [];
            }

            $qb->andWhere(\implode(' OR ', $conditions));
        }

        foreach ($parameters as $parameterKey => $parameterValue) {
            $qb->setParameter($parameterKey, $parameterValue);
        }

        foreach ($this->enhancers as $enhancer) {
            $enhancer->enhanceQuery($qb);
        }

        /** @var iterable<Product> */
        return $qb->getQuery()->toIterable();
    }

    public static function getIndex(): string
    {
        return 'admin';
    }
}
