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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Product\Domain\Model\ProductInterface;
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
    private const PAGE_RESOURCE_KEY = 'pages';
    private const ROUTE_PART_TAG = 'sulu.rlp.part';

    /**
     * @param array<string, scalar|null> $routeParams the params of the route field, "route_schema" among them
     */
    public function __construct(
        private string $routeType,
        private array $routeParams,
        private ResourceLocatorGeneratorInterface $resourceLocatorGenerator,
        private MetadataProviderInterface $formMetadataProvider,
    ) {
    }

    public function isPageBased(): bool
    {
        return self::PAGE_TREE_ROUTE === $this->routeType;
    }

    /**
     * How to pass the url, in the words of the configured route type. The example is what the route
     * generator of the admin makes of the content: it follows the route_schema, reads the same fields
     * and is not taken yet.
     *
     * @param array<string, mixed> $content the normalized content of the product
     */
    public function instruction(array $content = [], string $locale = 'en', ?string $resourceId = null): string
    {
        if ($this->isPageBased()) {
            return 'Pass content.url as {"page": {"uuid": "<page uuid>", "path": "<page path>"}, "suffix": "/<slug>"}. Copy the page from a sibling product (sulu_product_get).';
        }

        $parts = $this->routeParts($content, $locale);

        $example = [] !== $parts
            ? \sprintf('"%s"', $this->generateUrl($parts, $locale, $resourceId))
            : 'the pattern of a sibling product';

        return \sprintf('Pass content.url as the path string, e.g. %s. Copy the pattern from a sibling product (sulu_product_get).', $example);
    }

    /**
     * @param array<string, mixed> $content the normalized content, it makes the example
     */
    public function warning(array $content, string $locale, ?string $resourceId = null): string
    {
        return 'The product has no url, so it has no route and no page on the website. Set the url with sulu_product_update before publishing. ' . $this->instruction($content, $locale, $resourceId);
    }

    /**
     * The admin sends only the fields tagged "sulu.rlp.part" to the route generator, the title of a
     * product. Any other field would end up in the default route_schema, which implodes all of them.
     *
     * @param array<string, mixed> $content
     *
     * @return array<string, string>
     */
    private function routeParts(array $content, string $locale, string $formKey = ProductInterface::FORM_KEY): array
    {
        try {
            $metadata = $this->formMetadataProvider->getMetadata($formKey, $locale, []);
        } catch (\Throwable) {
            return [];
        }

        if (!$metadata instanceof FormMetadata) {
            return [];
        }

        $parts = [];
        foreach ($metadata->getFlatFieldMetadata() as $field) {
            $value = $content[$field->getName()] ?? null;
            if (!\is_string($value) || '' === \trim($value)) {
                continue;
            }

            foreach ($field->getTags() as $tag) {
                if (self::ROUTE_PART_TAG === $tag->getName()) {
                    $parts[$field->getName()] = $value;
                }
            }
        }

        return $parts;
    }

    /**
     * @param array<string, string> $parts
     */
    private function generateUrl(array $parts, string $locale, ?string $resourceId, ?string $pageUuid = null): string
    {
        // The configured schema starts with the product path and would repeat the path of the page.
        $routeSchema = null === $pageUuid ? ($this->routeParams['route_schema'] ?? null) : null;

        return $this->resourceLocatorGenerator->generate(new ResourceLocatorRequest(
            $parts,
            $locale,
            null,
            ProductInterface::RESOURCE_KEY,
            $resourceId,
            $pageUuid,
            null !== $pageUuid ? self::PAGE_RESOURCE_KEY : null,
            \is_string($routeSchema) ? $routeSchema : null,
            null !== $pageUuid,
        ));
    }

    /**
     * Fills a missing path url from the title, as the admin does while the editor types. A page based
     * url needs a page that only the agent can name, so it is left alone and the warning asks for it.
     *
     * @param array<string, mixed> $data
     * @param string $formKey the form whose "sulu.rlp.part" fields the admin sends for this kind of product
     *
     * @return array<string, mixed>
     */
    public function completeUrl(array $data, string $locale, string $formKey = ProductInterface::FORM_KEY): array
    {
        $title = $data['title'] ?? null;
        if ($this->isPageBased() || (isset($data['url']) && '' !== $data['url']) || !\is_string($title) || '' === \trim($title)) {
            return $data;
        }

        $parts = $this->routeParts($data, $locale, $formKey);
        if ([] === $parts) {
            $parts = ['title' => $title];
        }

        $data['url'] = $this->generateUrl($parts, $locale, null);

        return $data;
    }

    /**
     * Fills a missing suffix of a page based url from the title, so that the agent only has to name the page.
     * The suffix comes from the route generator with the page as parent and its default schema,
     * so it is not taken below the page yet, as long as the page has a url in this locale.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function completeUrlSuffix(array $data, string $locale, ?string $resourceId = null): array
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

        $pageUuid = $url['page']['uuid'] ?? null;
        if (!\is_string($pageUuid) || '' === $pageUuid) {
            return $data;
        }

        $parts = $this->routeParts($data, $locale);
        if ([] === $parts) {
            $parts = ['title' => $title];
        }

        $slug = \trim($this->generateUrl($parts, $locale, $resourceId, $pageUuid), '/');
        if ('' === $slug) {
            return $data;
        }

        $url['suffix'] = '/' . $slug;
        $data['url'] = $url;

        return $data;
    }
}
