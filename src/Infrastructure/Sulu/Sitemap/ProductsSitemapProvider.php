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

namespace Sulu\Product\Infrastructure\Sulu\Sitemap;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Bundle\WebsiteBundle\Sitemap\AbstractSitemapProvider;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapAlternateLink;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapUrl;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\PortalInformation;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @internal your code should not create direct dependencies on this implementation
 *           projects can create there own sitemap providers or use symfony
 *           dependency injection container to override this sitemap provider service
 *
 * @phpstan-type Product array{
 *     lastModified: \DateTimeImmutable|null,
 *     changed: \DateTimeImmutable,
 *     locale: string,
 *     availableLocales: string[]|null,
 *     slug: string,
 *     uuid: string
 * }
 * @phpstan-type AlternateRoute array{
 *     locale: string,
 *     slug: string,
 *     uuid: string
 * }
 */
class ProductsSitemapProvider extends AbstractSitemapProvider
{
    /**
     * @var EntityRepository<ProductInterface>
     */
    protected EntityRepository $entityRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly string $environment,
    ) {
        $repository = $entityManager->getRepository(ProductInterface::class);

        $this->entityRepository = $repository;
    }

    /**
     * @return SitemapUrl[]
     */
    public function build($page, $scheme, $host): array
    {
        $portalInformations = $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            $host,
            $this->environment,
        );

        $result = [];

        foreach ($portalInformations as $portalInformation) {
            /** @var Localization|null $localization */
            $localization = $portalInformation->getLocalization();

            if (!$localization) {
                continue;
            }

            $locale = $portalInformation->getLocalization()->getLocale();
            /** @var string $webspaceKey */
            $webspaceKey = $portalInformation->getWebspaceKey();
            $productIterator = $this->getProducts($webspaceKey, $locale);
            $alternateRoutesIterator = $this->getAlternateRoutes($webspaceKey, $locale);
            $alternateRoutes = [];
            $products = [];
            $alternateProducts = [];

            /** @var AlternateRoute $alternateRoute */
            foreach ($alternateRoutesIterator as $alternateRoute) {
                $alternateLocale = $alternateRoute['locale'];
                $alternateSlug = $alternateRoute['slug'];
                $productUuid = $alternateRoute['uuid'];

                if (!\array_key_exists($productUuid, $alternateRoutes)) {
                    $alternateRoutes[$productUuid] = [];
                }

                if (!\array_key_exists($alternateLocale, $alternateRoutes[$productUuid])) {
                    $alternateRoutes[$productUuid][$alternateLocale] = [];
                }

                $alternateRoutes[$productUuid][$alternateLocale][] = $alternateSlug;
            }

            /** @var Product $product */
            foreach ($productIterator as $product) {
                $products[] = $product;

                $productUuid = $product['uuid'];
                $alternateLocales = \array_filter(
                    $product['availableLocales'] ?? [],
                    fn ($availableLocale) => $availableLocale !== $locale,
                );

                $productAlternateRoutes = [];
                foreach ($alternateLocales as $availableLocale) {
                    if (isset($alternateRoutes[$productUuid][$availableLocale])) {
                        $productAlternateRoutes[$availableLocale] = $alternateRoutes[$productUuid][$availableLocale];
                    }
                }

                if (!empty($productAlternateRoutes)) {
                    $alternateProducts[$productUuid] = $productAlternateRoutes;
                } else {
                    unset($alternateProducts[$productUuid]);
                }
            }

            foreach ($products as $product) {
                // Todo: Add access control check.

                $sitemapUrl = $this->generateSitemapUrl($product, $alternateProducts, $portalInformation, $host, $scheme);

                if (!$sitemapUrl) {
                    continue;
                }

                $result[] = $sitemapUrl;
            }
        }

        return $result;
    }

    /**
     * @return iterable<Product>
     */
    private function getProducts(string $webspaceKey, string $locale)
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('product');

        $queryBuilder->distinct()->join('product.dimensionContents', 'dimensionContent', 'WITH', '
            dimensionContent.locale = :locale
            AND dimensionContent.stage = :stage
            AND dimensionContent.version = :version
            AND dimensionContent.seoHideInSitemap = :hide
        ')
            ->leftJoin('dimensionContent.additionalWebspaces', 'additionalWebspace')
            ->leftJoin('product.dimensionContents', 'unLocalizedDimensionContent', 'WITH', '
            unLocalizedDimensionContent.locale IS NULL
            AND unLocalizedDimensionContent.stage = :stage
            AND unLocalizedDimensionContent.version = :version
            AND unLocalizedDimensionContent.seoHideInSitemap = :hide
        ')
            ->leftJoin('dimensionContent.route', 'route')
            ->andWhere('dimensionContent.mainWebspace = :webspaceKey OR additionalWebspace.additionalWebspace = :webspaceKey')
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('webspaceKey', $webspaceKey)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('hide', false);

        $queryBuilder->select('dimensionContent.lastModified');
        $queryBuilder->addSelect('dimensionContent.changed');
        $queryBuilder->addSelect('dimensionContent.locale');

        $queryBuilder->addSelect('unLocalizedDimensionContent.availableLocales');

        $queryBuilder->addSelect('route.slug');

        $queryBuilder->addSelect('product.uuid');

        $queryBuilder->orderBy('route.slug', 'ASC');

        /**
         * @var iterable<Product>
         */
        return $queryBuilder->getQuery()->toIterable();
    }

    /**
     * @return iterable<AlternateRoute>
     */
    private function getAlternateRoutes(string $webspaceKey, string $locale): iterable
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('product');

        $queryBuilder->distinct()->leftJoin('product.dimensionContents', 'dimensionContent', 'WITH', '
            dimensionContent.locale != :locale
            AND dimensionContent.locale IS NOT NULL
            AND dimensionContent.stage = :stage
            AND dimensionContent.version = :version
            AND dimensionContent.seoHideInSitemap = :hide
        ')
            ->leftJoin('dimensionContent.additionalWebspaces', 'additionalWebspace')
            ->leftJoin('dimensionContent.route', 'route')
            ->andWhere('dimensionContent.mainWebspace = :webspaceKey OR additionalWebspace.additionalWebspace = :webspaceKey')
            ->setParameter('locale', $locale)
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('webspaceKey', $webspaceKey)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('hide', false);

        $queryBuilder->select('dimensionContent.locale');
        $queryBuilder->addSelect('route.slug');
        $queryBuilder->addSelect('product.uuid');

        $queryBuilder->orderBy('route.slug', 'ASC');

        /**
         * @var iterable<AlternateRoute>
         */
        return $queryBuilder->getQuery()->toIterable();
    }

    /**
     * @param Product $product
     * @param array<string, array<string, string[]>> $alternateProduct
     */
    private function generateSitemapUrl(
        array $product,
        array $alternateProduct,
        PortalInformation $portalInformation,
        string $host,
        string $scheme,
    ): ?SitemapUrl {
        $changed = $product['changed'];
        /** @var string|null $webspaceKey */
        $webspaceKey = $portalInformation->getWebspaceKey();

        if (!empty($product['lastModified'])) {
            $changed = $product['lastModified'];
        }

        $url = $this->webspaceManager->findUrlByResourceLocator(
            $product['slug'],
            $this->environment,
            $product['locale'],
            $webspaceKey,
            $host,
            $scheme,
        );

        if (!$url) {
            return null;
        }

        $defaultLocale = $portalInformation
            ->getWebspace()
            ->getDefaultLocalization()
            ->getLocale(Localization::DASH);

        $sitemapUrl = new SitemapUrl(
            $url,
            $product['locale'],
            $defaultLocale,
            $changed,
        );

        if ($alternateProduct[$product['uuid']] ?? null) {
            foreach ($alternateProduct[$product['uuid']] as $alternateLocale => $alternateSlugs) {
                foreach ($alternateSlugs as $alternateSlug) {
                    $alternateUrl = $this->webspaceManager->findUrlByResourceLocator(
                        $alternateSlug,
                        $this->environment,
                        $alternateLocale,
                        $webspaceKey,
                        $host,
                        $scheme,
                    );

                    if ($alternateUrl) {
                        $sitemapUrl->addAlternateLink(new SitemapAlternateLink($alternateUrl, $alternateLocale));
                    }
                }
            }
        }

        return $sitemapUrl;
    }

    public function getAlias(): string
    {
        return 'products';
    }
}
