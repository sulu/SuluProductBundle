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

namespace Sulu\Product\Infrastructure\Sulu\Route;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\CacheLifetimeMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TemplateMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeResolverInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Application\Routing\Matcher\RouteDefaultsProviderInterface;
use Sulu\Route\Domain\Exception\MissingRequestContextParameterException;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal this class is internal and should not be extended or relied on in custom code
 */
class ProductRouteDefaultsProvider implements RouteDefaultsProviderInterface
{
    /** The route default holding the variant whose URL renders its product. */
    public const VARIANT_ATTRIBUTE = 'variant';

    /**
     * @param string|null $variantQueryParameter the query parameter naming the variant a product URL
     *                                           renders, null when each variant has a route of its own
     */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentAggregatorInterface $contentAggregator,
        private MetadataProviderRegistry $metadataProviderRegistry,
        private CacheLifetimeResolverInterface $cacheLifetimeResolver,
        private RouteGeneratorInterface $routeGenerator,
        private RequestStack $requestStack,
        private ?string $variantQueryParameter = null,
    ) {
    }

    public function getDefaults(Route $route): array
    {
        $id = $route->getResourceId();
        $locale = $route->getLocale();

        $dimensionContent = $this->loadContent($id, $locale)
            ?? throw new NotFoundHttpException(\sprintf('No product found for id "%s" and locale "%s"', $id, $locale));

        $variantDefaults = [];
        $parent = $dimensionContent->getResource()->getParent();
        if (null !== $parent) {
            // a variant has no content of its own, its URL renders its product
            $variantDefaults[self::VARIANT_ATTRIBUTE] = $dimensionContent;
            $dimensionContent = $this->loadContent($parent->getUuid(), $locale)
                ?? throw new NotFoundHttpException(\sprintf('No published parent product found for variant id "%s" and locale "%s"', $id, $locale));
        } elseif (null !== $this->variantQueryParameter) {
            $variant = $this->findQueriedVariant($id, $locale);
            if (null !== $variant) {
                $variantDefaults[self::VARIANT_ATTRIBUTE] = $variant;
            }
        }

        $contentLocale = $dimensionContent->getLocale();
        if (!$contentLocale) {
            throw new NotFoundHttpException(\sprintf('No product found for id "%s" and locale "%s"', $id, $locale));
        }

        $templateKey = $dimensionContent->getTemplateKey();
        if (!$templateKey) {
            throw new NotFoundHttpException(\sprintf('No template found for id "%s" and locale "%s"', $id, $locale));
        }

        $templateMetadata = $this->resolveTemplateMetadata($dimensionContent::getTemplateType(), $templateKey, $contentLocale);

        $defaults = [
            'object' => $dimensionContent,
            'view' => $templateMetadata->getView(),
            '_controller' => $templateMetadata->getController(),
            ...$variantDefaults,
        ];

        $canonicalUrl = $this->resolveCanonicalUrl($route, $dimensionContent);
        if (null !== $canonicalUrl) {
            $defaults['_seo'] = ['canonicalUrl' => $canonicalUrl];
        }

        $cacheLifetime = $this->getCacheLifetime($templateMetadata);
        if (null !== $cacheLifetime) {
            $defaults[CacheLifetimeRequestStore::ATTRIBUTE_KEY] = $cacheLifetime;
        }

        return $defaults;
    }

    /**
     * The published variant named by `?<parameter>=<code>`, or null if the request has none or the
     * product has no published variant with that code.
     */
    private function findQueriedVariant(string $productUuid, string $locale): ?ProductDimensionContentInterface
    {
        // all() and not get(), which throws a 400 on `variant[]=x`
        $code = $this->requestStack->getCurrentRequest()?->query->all()[$this->variantQueryParameter] ?? null;

        if (!\is_string($code) || '' === $code) {
            return null;
        }

        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        $variants = $this->productRepository->findBy(
            [
                'parent' => $productUuid,
                'code' => $code,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => ['dimensionAttributes' => $dimensionAttributes]],
        );

        foreach ($variants as $variant) {
            try {
                /** @var ProductDimensionContentInterface */
                return $this->contentAggregator->aggregate($variant, $dimensionAttributes);
            } catch (ContentNotFoundException) {
                return null;
            }
        }

        return null;
    }

    /**
     * With a query parameter every `?<parameter>=<value>` is a page of its own, so the product URL
     * is the canonical one unless the editor set another.
     */
    private function resolveCanonicalUrl(Route $route, ProductDimensionContentInterface $dimensionContent): ?string
    {
        if (null === $this->variantQueryParameter || !\in_array($dimensionContent->getSeoCanonicalUrl(), [null, ''], true)) {
            return null;
        }

        try {
            return $this->routeGenerator->generate(
                $route->getSlug(),
                $route->getLocale(),
                $route->getWebspace(),
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (MissingRequestContextParameterException) {
            return null;
        }
    }

    private function loadContent(string $id, string $locale): ?ProductDimensionContentInterface
    {
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        $product = $this->productRepository->findOneBy(
            [
                'uuid' => $id,
            ],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'dimensionAttributes' => $dimensionAttributes,
                    'selects' => [
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_TAGS => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES_TRANSLATION => true,
                    ],
                ],
                ProductRepositoryInterface::SELECT_PRODUCT_ATTRIBUTES => true,
            ],
        );

        if (null === $product) {
            return null;
        }

        try {
            return $this->contentAggregator->aggregate($product, $dimensionAttributes);
        } catch (ContentNotFoundException) {
            return null;
        }
    }

    private function getCacheLifetime(TemplateMetadata $templateMetadata): ?int
    {
        $cacheLifetime = $templateMetadata->getCacheLifetime();
        if (!$cacheLifetime instanceof CacheLifetimeMetadata) {
            return null;
        }

        $cacheLifeTimeType = $cacheLifetime->getType();
        $cacheLifeTimeValue = $cacheLifetime->getValue();

        if (!$this->cacheLifetimeResolver->supports($cacheLifeTimeType, $cacheLifeTimeValue)) {
            throw new \InvalidArgumentException(\sprintf('Invalid cacheLifeTime in route default provider: %s', \json_encode([
                'type' => $cacheLifeTimeType,
                'value' => $cacheLifeTimeValue,
            ], flags: \JSON_THROW_ON_ERROR)));
        }

        return $this->cacheLifetimeResolver->resolve($cacheLifeTimeType, $cacheLifeTimeValue);
    }

    private function resolveTemplateMetadata(string $type, string $templateKey, string $locale): TemplateMetadata
    {
        $typedMetadata = $this->metadataProviderRegistry->getMetadataProvider('form')
            ->getMetadata($type, $locale, []);

        if (!$typedMetadata instanceof TypedFormMetadata) {
            throw new \RuntimeException(\sprintf('Could not find metadata "%s" of type "%s".', 'form', $type));
        }

        $metadata = $typedMetadata->getForms()[$templateKey] ?? null;

        if (!$metadata instanceof FormMetadata) {
            throw new \RuntimeException(\sprintf('Could not find form metadata "%s" of type "%s".', $templateKey, $type));
        }

        $templateMetadata = $metadata->getTemplate();

        if (!$templateMetadata instanceof TemplateMetadata) {
            throw new \RuntimeException(\sprintf('Could not find template metadata "%s" of type "%s".', $templateKey, $type));
        }

        return $templateMetadata;
    }
}
