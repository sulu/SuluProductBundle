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
use Sulu\Product\Domain\Model\ProductDimensionContentAdditionalWebspace;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexProviderEnhancerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @phpstan-type Product array{
 *     productId: int,
 *     changed: \DateTimeImmutable,
 *     title: string,
 *     locale: string,
 *     mainWebspace: string|null,
 *     additionalWebspaces: string[]|null,
 *     slug: string,
 *     dimensionContentId: int,
 *     authored: \DateTimeImmutable|null,
 * }
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *            use Symfony Dependency Injection to override or create your own ReindexProvider instead
 */
final class WebsiteProductReindexProvider implements ReindexProviderInterface
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
        private iterable $enhancers = [],
    ) {
        $dimensionContentRepository = $entityManager->getRepository(ProductDimensionContentInterface::class);
        $additionalWebspacesRepository = $entityManager->getRepository(ProductDimensionContentAdditionalWebspace::class);

        $this->dimensionContentRepository = $dimensionContentRepository;
        $this->additionalWebspacesRepository = $additionalWebspacesRepository;
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
            $dimensionContentIds = \array_column($batch, 'dimensionContentId');
            $additionalWebspacesResult = $this->loadAdditionalWebspaces($dimensionContentIds);

            /** @var Product $product */
            foreach ($batch as $product) {
                $authoredAt = $product['authored'] ?? $product['changed'];
                $webspaces = $product['mainWebspace'] ? [$product['mainWebspace']] : [];

                foreach ($additionalWebspacesResult as $additionalWebspaceRow) {
                    if ($additionalWebspaceRow['productDimensionContentId'] === $product['dimensionContentId'] && !\in_array($additionalWebspaceRow['webspace'], $webspaces, true)) {
                        $webspaces[] = $additionalWebspaceRow['webspace'];
                    }
                }

                $data = [
                    'id' => ProductInterface::RESOURCE_KEY . '__' . ((string) $product['productId']) . '__' . $product['locale'],
                    'resourceKey' => ProductInterface::RESOURCE_KEY,
                    'resourceId' => (string) $product['productId'],
                    'locale' => $product['locale'],
                    'webspaces' => $webspaces,
                    'title' => '',
                    'url' => $product['slug'],
                    'content' => [],
                    'mediaId' => '',
                    'authoredAt' => $authoredAt->format('c'),
                    'metadata' => [],
                ];

                foreach ($this->enhancers as $enhancer) {
                    $data = $enhancer->enhanceDocument($product, $data);
                }

                if ('' === $data['title']) {
                    $data['title'] = $product['title'];
                }

                yield $data;
            }

            $offset += self::BATCH_SIZE;
            $batch = $this->loadBatch($identifiers, $offset);
        }
    }

    /**
     * @param string[] $identifiers
     *
     * @return array<int, Product>
     */
    private function loadBatch(array $identifiers, int $offset): array
    {
        $queryBuilder = $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->leftJoin('dimensionContent.route', 'route')
            ->select('IDENTITY(dimensionContent.product) AS productId')
            ->addSelect('dimensionContent.authored')
            ->addSelect('dimensionContent.changed')
            ->addSelect('dimensionContent.title')
            ->addSelect('dimensionContent.locale')
            ->addSelect('dimensionContent.mainWebspace')
            ->addSelect('dimensionContent.id AS dimensionContentId')
            ->addSelect('route.slug')
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

        /** @var array<int, Product> */
        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param int[] $dimensionContentIds
     *
     * @return array<int, array{productDimensionContentId: int, webspace: string}>
     */
    private function loadAdditionalWebspaces(array $dimensionContentIds = []): array
    {
        if (0 === \count($dimensionContentIds)) {
            return [];
        }

        $queryBuilder = $this->additionalWebspacesRepository->createQueryBuilder('additionalWebspace')
            ->select('IDENTITY(additionalWebspace.productDimensionContent) AS productDimensionContentId')
            ->addSelect('additionalWebspace.additionalWebspace AS webspace')
            ->where('additionalWebspace.productDimensionContent IN (:dimensionContentIds)')
            ->setParameter('dimensionContentIds', $dimensionContentIds);

        return $queryBuilder->getQuery()->getResult();
    }

    public static function getIndex(): string
    {
        return 'website';
    }
}
