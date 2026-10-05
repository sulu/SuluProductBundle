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

namespace Sulu\Product\Application\Mcp;

use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanupInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGeneratorInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorRequest;

/**
 * The product route is only created when the saved data carries a "url". Without it the product
 * has no page on the website, and nothing fails to tell the agent. The shape of the url depends
 * on the route type of the installation: a "page_tree_route" takes a page and a suffix, every
 * other type takes the path as a string.
 *
 * @internal
 */
final readonly class ProductUrlHelper
{
    private const PAGE_TREE_ROUTE = 'page_tree_route';

    /**
     * @param array<string, scalar|null> $routeParams the params of the route field, "route_schema" among them
     */
    public function __construct(
        private string $routeType,
        private array $routeParams,
        private PathCleanupInterface $pathCleanup,
        private ResourceLocatorGeneratorInterface $resourceLocatorGenerator,
    ) {
    }

    public function isPageBased(): bool
    {
        return self::PAGE_TREE_ROUTE === $this->routeType;
    }

    /**
     * How to pass the url, in the words of the configured route type. The example is what the route
     * generator of the admin makes of the title: it follows the route_schema and is not taken yet.
     */
    public function instruction(?string $title = null, string $locale = 'en', ?string $resourceId = null): string
    {
        if ($this->isPageBased()) {
            return 'Pass content.url as {"page": {"uuid": "<page uuid>", "path": "<page path>"}, "suffix": "/<slug>"}. Copy the page from a sibling product (sulu_product_get).';
        }

        $example = null !== $title && '' !== \trim($title)
            ? \sprintf('"%s"', $this->generateUrl($title, $locale, $resourceId))
            : 'the pattern of a sibling product';

        return \sprintf('Pass content.url as the path string, e.g. %s. Copy the pattern from a sibling product (sulu_product_get).', $example);
    }

    /**
     * @param array<string, mixed> $content the normalized content, its title makes the example
     */
    public function warning(array $content, string $locale, ?string $resourceId = null): string
    {
        $title = $content['title'] ?? null;

        return 'The product has no url, so it has no route and no page on the website. Set the url with sulu_product_update before publishing. ' . $this->instruction(\is_string($title) ? $title : null, $locale, $resourceId);
    }

    private function generateUrl(string $title, string $locale, ?string $resourceId): string
    {
        $routeSchema = $this->routeParams['route_schema'] ?? null;

        return $this->resourceLocatorGenerator->generate(new ResourceLocatorRequest(
            ['title' => $title],
            $locale,
            null,
            ProductInterface::RESOURCE_KEY,
            $resourceId,
            null,
            null,
            \is_string($routeSchema) ? $routeSchema : null,
        ));
    }

    /**
     * Fills a missing suffix of a page based url from the title, so that the agent only has to name the page.
     * The slug comes from the same service as the route generation, so it matches the url of the admin.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function completeUrlSuffix(array $data, string $locale): array
    {
        $url = $data['url'] ?? null;
        $title = $data['title'] ?? null;
        if (!$this->isPageBased() || !\is_array($url) || !\is_string($title) || !\is_array($url['page'] ?? null)) {
            return $data;
        }

        $suffix = $url['suffix'] ?? null;
        if (\is_string($suffix) && '' !== \trim($suffix, '/ ')) {
            return $data;
        }

        $slug = \trim($this->pathCleanup->cleanup($title, $locale), '/');
        if ('' === $slug) {
            return $data;
        }

        $url['suffix'] = '/' . $slug;
        $data['url'] = $url;

        return $data;
    }
}
