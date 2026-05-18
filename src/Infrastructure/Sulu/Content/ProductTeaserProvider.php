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

use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Bundle\AdminBundle\Teaser\Configuration\TeaserConfiguration;
use Sulu\Bundle\AdminBundle\Teaser\Provider\TeaserProviderInterface;
use Sulu\Bundle\AdminBundle\Teaser\Teaser;
use Sulu\Bundle\AdminBundle\Teaser\TeaserTagPropertyExtractor;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentEnhancer\ContentEnhancerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductTeaserProvider implements TeaserProviderInterface
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected ContentAggregatorInterface $contentAggregator,
        protected ContentEnhancerInterface $contentEnhancer,
        protected TranslatorInterface $translator,
        protected TeaserTagPropertyExtractor $teaserTagPropertyExtractor,
    ) {
    }

    public function getConfiguration(): TeaserConfiguration
    {
        return new TeaserConfiguration(
            $this->translator->trans('sulu_product.product', [], 'admin'),
            ProductInterface::RESOURCE_KEY,
            'table',
            ['title'],
            $this->translator->trans('sulu_product.single_selection_overlay_title', [], 'admin'),
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return Teaser[]
     */
    public function find(array $ids, $locale): array
    {
        if (0 === \count($ids)) {
            return [];
        }

        $products = $this->findProductsByUuids($ids, $locale);

        $teasers = [];
        foreach ($products as $product) {
            $teaser = $this->createTeaserFromProduct($product, $locale);
            if (null !== $teaser) {
                $teasers[] = $teaser;
            }
        }

        return $teasers;
    }

    /**
     * @param array<string> $uuids
     *
     * @return array<ProductInterface>
     */
    private function findProductsByUuids(array $uuids, string $locale): array
    {
        /** @var array<ProductInterface> $products */
        $products = \iterator_to_array($this->productRepository->findBy(
            filters: [
                'uuids' => $uuids,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            selects: [
                ProductRepositoryInterface::SELECT_ARTICLE_CONTENT => [
                    DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
                ],
            ]
        ));

        // Sort by original order
        $uuidPositions = \array_flip($uuids);
        \usort(
            $products,
            static fn (ProductInterface $a, ProductInterface $b) => ($uuidPositions[$a->getUuid()] ?? 0) - ($uuidPositions[$b->getUuid()] ?? 0)
        );

        return $products;
    }

    private function createTeaserFromProduct(ProductInterface $product, string $locale): ?Teaser
    {
        $dimensionContent = $this->resolveDimensionContent($product, $locale);
        if (null === $dimensionContent) {
            return null;
        }

        /** @var ProductDimensionContentInterface $dimensionContent */
        $dimensionContent = $this->contentEnhancer->enhance($dimensionContent);

        $url = $this->resolveUrl($dimensionContent);
        if (null === $url) {
            return null;
        }

        return new Teaser(
            $product->getUuid(),
            ProductInterface::RESOURCE_KEY,
            $locale,
            $this->resolveTitle($dimensionContent) ?? '',
            $this->resolveDescription($dimensionContent) ?? '',
            $this->resolveMoreText($dimensionContent) ?? '',
            $url,
            $this->resolveMediaId($dimensionContent),
            $this->getAttributes($dimensionContent),
        );
    }

    protected function resolveDimensionContent(ProductInterface $product, string $locale): ?ProductDimensionContentInterface
    {
        try {
            /** @var ProductDimensionContentInterface $dimensionContent */
            $dimensionContent = $this->contentAggregator->aggregate($product, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ContentNotFoundException) {
            return null;
        }

        return $dimensionContent;
    }

    protected function resolveUrl(ProductDimensionContentInterface $dimensionContent): ?string
    {
        $route = $dimensionContent->getRoute();

        return $route?->getSlug();
    }

    protected function resolveTitle(ProductDimensionContentInterface $dimensionContent): ?string
    {
        $title = $dimensionContent->getExcerptTitle() ?? $dimensionContent->getTitle();

        return '' !== ($title ?? '') ? $title : null;
    }

    protected function resolveDescription(ProductDimensionContentInterface $dimensionContent): ?string
    {
        $description = $dimensionContent->getExcerptDescription();
        if (null !== $description && '' !== $description) {
            return \strip_tags($description);
        }

        // Fallback to tagged property
        $templateKey = $dimensionContent->getTemplateKey();
        $locale = $dimensionContent->getLocale();
        if (null === $templateKey || null === $locale) {
            return null;
        }

        $description = $this->teaserTagPropertyExtractor->extractDescription(
            ProductInterface::TEMPLATE_TYPE,
            $templateKey,
            $locale,
            $dimensionContent->getTemplateData()
        );

        return null !== $description ? \strip_tags($description) : null;
    }

    protected function resolveMoreText(ProductDimensionContentInterface $dimensionContent): ?string
    {
        $moreText = $dimensionContent->getExcerptMore();

        return '' !== ($moreText ?? '') ? $moreText : null;
    }

    protected function resolveMediaId(ProductDimensionContentInterface $dimensionContent): ?int
    {
        $mediaId = $dimensionContent->getExcerptImage()['id'] ?? null;
        if (null !== $mediaId) {
            return $mediaId;
        }

        // Fallback to tagged property
        $templateKey = $dimensionContent->getTemplateKey();
        $locale = $dimensionContent->getLocale();
        if (null === $templateKey || null === $locale) {
            return null;
        }

        return $this->teaserTagPropertyExtractor->extractMediaId(
            ProductInterface::TEMPLATE_TYPE,
            $templateKey,
            $locale,
            $dimensionContent->getTemplateData()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAttributes(ProductDimensionContentInterface $dimensionContent): array
    {
        return [
            'uuid' => $dimensionContent->getResourceId(),
            'webspace' => $dimensionContent->getMainWebspace(),
            'additionalWebspaces' => $dimensionContent->getAdditionalWebspaces(),
        ];
    }
}
