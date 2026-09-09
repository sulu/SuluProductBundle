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

namespace Sulu\Product\UserInterface\Controller\Website;

use Sulu\Bundle\WebsiteBundle\Resolver\TemplateAttributeResolverInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Product\Application\Search\ProductSearcher;
use Sulu\Product\Application\Search\ProductSearchQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Renders the webspace's `product_search` template. Projects extend this class or replace the
 * `sulu_product.controller.website_search` service to change parameters, filters or output.
 */
class ProductSearchController
{
    public const TEMPLATE_TYPE = 'product_search';

    /**
     * Largest offset plus limit the request may ask for. Elasticsearch rejects a deeper window
     * (`index.max_result_window`) with a search-phase exception.
     */
    public const MAX_WINDOW = 10_000;
    public const MAX_LIMIT = 100;

    public function __construct(
        protected readonly ProductSearcher $productSearcher,
        protected readonly RequestAnalyzerInterface $requestAnalyzer,
        protected readonly Environment $twig,
        protected readonly TemplateAttributeResolverInterface $templateAttributeResolver,
        protected readonly string $variantQueryParameter,
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $localization = $this->requestAnalyzer->getCurrentLocalization();
        $webspace = $this->requestAnalyzer->getWebspace();
        if (null === $localization || null === $webspace) {
            throw new NotFoundHttpException();
        }

        $format = (string) $request->getRequestFormat();
        $template = $webspace->getTemplate(self::TEMPLATE_TYPE, $format);
        if (!$template || !$this->twig->getLoader()->exists($template)) {
            throw new NotFoundHttpException(\sprintf('Webspace "%s" has no "%s" template for format "%s".', $webspace->getKey(), self::TEMPLATE_TYPE, $format));
        }

        $query = $this->createQuery($request, $localization->getLocale(), $webspace->getKey());
        $result = $this->productSearcher->search($query);

        $hits = [];
        foreach ($result as $document) {
            $hits[] = $document;
        }

        $parameters = $this->templateAttributeResolver->resolve([
            'query' => $query->term,
            'hits' => $hits,
            'total' => $result->total(),
            'facets' => $result->facets(),
            'page' => $query->page,
            'limit' => $query->limit,
            'filters' => $query->equals,
            'ranges' => $query->ranges,
            'variantQueryParameter' => $this->variantQueryParameter,
        ]);

        return new Response($this->twig->render($template, $parameters));
    }

    /**
     * Request contract: `q` term, `page`, `limit`, `filter[<field>]=value` (repeatable as
     * `filter[<field>][]`), `range[<field>][min|max]`, `facet[]=<field>`, `minmax[]=<field>`,
     * `sort[<field>]=asc|desc`. A parameter of the wrong shape falls back to its default.
     * Override to change the contract.
     */
    protected function createQuery(Request $request, string $locale, string $webspace): ProductSearchQuery
    {
        $parameters = $request->query->all();

        $term = $parameters['q'] ?? '';

        $limit = $this->resolveInt($parameters['limit'] ?? null, 24, self::MAX_LIMIT);

        return new ProductSearchQuery(
            locale: $locale,
            webspace: $webspace,
            term: \is_string($term) ? $term : '',
            equals: $this->resolveEquals($parameters['filter'] ?? null),
            ranges: $this->resolveRanges($parameters['range'] ?? null),
            countFacets: $this->resolveFields($parameters['facet'] ?? null),
            minMaxFacets: $this->resolveFields($parameters['minmax'] ?? null),
            page: $this->resolveInt($parameters['page'] ?? null, 1, \max(1, \intdiv(self::MAX_WINDOW, $limit))),
            limit: $limit,
            sortBy: $this->resolveSortBy($parameters['sort'] ?? null),
        );
    }

    /**
     * @return array<string, string|string[]>
     */
    protected function resolveEquals(mixed $filter): array
    {
        if (!\is_array($filter)) {
            return [];
        }

        $equals = [];
        foreach ($filter as $field => $value) {
            if (\is_string($value)) {
                $equals[(string) $field] = $value;

                continue;
            }

            $values = \is_array($value) ? \array_values(\array_filter($value, \is_string(...))) : [];
            if ([] !== $values) {
                $equals[(string) $field] = $values;
            }
        }

        return $equals;
    }

    /**
     * @return array<string, array{min?: float, max?: float}>
     */
    protected function resolveRanges(mixed $range): array
    {
        if (!\is_array($range)) {
            return [];
        }

        $ranges = [];
        foreach ($range as $field => $bounds) {
            if (!\is_array($bounds)) {
                continue;
            }

            foreach (['min', 'max'] as $bound) {
                if (isset($bounds[$bound]) && \is_numeric($bounds[$bound])) {
                    $ranges[(string) $field][$bound] = (float) $bounds[$bound];
                }
            }
        }

        return $ranges;
    }

    /**
     * @return string[]
     */
    protected function resolveFields(mixed $fields): array
    {
        if (!\is_array($fields)) {
            return [];
        }

        return \array_values(\array_filter($fields, \is_string(...)));
    }

    /**
     * @return array<string, 'asc'|'desc'>
     */
    protected function resolveSortBy(mixed $sort): array
    {
        if (!\is_array($sort)) {
            return [];
        }

        $sortBy = [];
        foreach ($sort as $field => $direction) {
            if ('asc' === $direction || 'desc' === $direction) {
                $sortBy[(string) $field] = $direction;
            }
        }

        return $sortBy;
    }

    protected function resolveInt(mixed $value, int $default, int $max): int
    {
        if (!\is_numeric($value)) {
            return $default;
        }

        return \max(1, \min($max, (int) $value));
    }
}
