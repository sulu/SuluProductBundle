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
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Lists what a saved product still lacks and which tool parameter fills it. An agent that gets
 * "success" back otherwise has no way to know what a complete product looks like.
 * The result is advice only: nothing here blocks or changes a save.
 *
 * @internal
 */
final readonly class ProductCompletenessChecker
{
    private const MEDIA_TYPES = ['single_media_selection', 'media_selection'];
    private const EXCERPT_FORM_KEY = 'content_excerpt_metadata';

    public function __construct(
        private MetadataProviderInterface $formMetadataProvider,
        private WebspaceManagerInterface $webspaceManager,
        private ProductUrlHelper $urlHelper,
    ) {
    }

    /**
     * @param array<string, mixed> $normalized the normalized content of $locale
     *
     * @return list<string>
     */
    public function check(ProductInterface $product, array $normalized, string $locale): array
    {
        $recommendations = [];

        // A variant has its own code, url and locales, but no content, category, tag or SEO. Its details are set with sulu_product_variant_update.
        if ($product->isType(ProductInterface::TYPE_VARIANT)) {
            if (self::isEmpty($normalized['code'] ?? null)) {
                $recommendations[] = 'No code. Pass code to sulu_product_variant_update with the SKU from the datasheet. If there is none, derive one that follows the pattern of the codes of the other variants and is not taken yet.';
            }

            if (!self::hasUrl($normalized)) {
                $recommendations[] = 'No url. The url of a variant is set in the admin, because the variant tools have no url parameter.';
            }

            $details = \is_array($normalized['details'] ?? null) ? $normalized['details'] : [];
            foreach ($this->mediaFields(ProductInterface::FORM_KEY, 'details', $locale) as $field => $multiple) {
                if (self::isEmptyMedia($details[$field] ?? null)) {
                    $recommendations[] = self::mediaRecommendation('details', $field, $multiple);
                }
            }

            $missingLocales = $this->missingLocales($product, $locale);
            if ([] !== $missingLocales) {
                $recommendations[] = \sprintf(
                    'No content in %s. Call sulu_product_variant_update with each of these locales and pass the title.',
                    \implode(', ', \array_map(static fn (string $missing): string => '"' . $missing . '"', $missingLocales)),
                );
            }

            return $recommendations;
        }

        $withVariants = $product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        // A parent that holds variants has no code or url of its own.
        if (!$withVariants && self::isEmpty($normalized['code'] ?? null)) {
            $recommendations[] = 'No code. Pass code with the SKU from the datasheet. If there is none, derive one that follows the pattern of the codes of existing products (sulu_product_list) and is not taken yet.';
        }

        if (!$withVariants && !self::hasUrl($normalized)) {
            $recommendations[] = 'No url. ' . $this->urlHelper->instruction();
        }

        if (self::isEmpty($normalized['excerptCategories'] ?? null)) {
            $recommendations[] = 'No categories. Pick the fitting ones from sulu_category_list and pass their ids as excerpt.excerptCategories. They do not have to come from the source.';
        }

        if (self::isEmpty($normalized['excerptTags'] ?? null)) {
            $recommendations[] = 'No tags. Pick the fitting ones from sulu_tag_list and pass their ids as excerpt.excerptTags. They do not have to come from the source.';
        }

        $seo = \is_array($normalized['seo'] ?? null) ? $normalized['seo'] : [];
        foreach (['title', 'description'] as $field) {
            if (self::isEmpty($seo[$field] ?? null)) {
                $recommendations[] = \sprintf('No SEO %s. Pass seo.%s.', $field, $field);
            }
        }

        $details = \is_array($normalized['details'] ?? null) ? $normalized['details'] : [];
        foreach ($this->mediaFields(ProductInterface::FORM_KEY, 'details', $locale) as $field => $multiple) {
            if (self::isEmptyMedia($details[$field] ?? null)) {
                $recommendations[] = self::mediaRecommendation('details', $field, $multiple);
            }
        }

        $excerpt = \is_array($normalized['excerpt'] ?? null) ? $normalized['excerpt'] : [];
        foreach ($this->mediaFields(self::EXCERPT_FORM_KEY, 'excerpt', $locale) as $field => $multiple) {
            if (self::isEmptyMedia($excerpt[$field] ?? null)) {
                $recommendations[] = self::mediaRecommendation('excerpt', $field, $multiple);
            }
        }

        $missingLocales = $this->missingLocales($product, $locale);
        if ([] !== $missingLocales) {
            $recommendations[] = \sprintf(
                'No content in %s. Call sulu_product_update with each of these locales and pass title, content, excerpt and seo.',
                \implode(', ', \array_map(static fn (string $missing): string => '"' . $missing . '"', $missingLocales)),
            );
        }

        return $recommendations;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public static function hasUrl(array $normalized): bool
    {
        $url = $normalized['url'] ?? null;

        if (\is_string($url)) {
            return '' !== $url;
        }

        if (!\is_array($url)) {
            return false;
        }

        $page = $url['page'] ?? null;

        return \is_array($page) && \is_string($page['uuid'] ?? null) && '' !== $page['uuid'];
    }

    /**
     * @return array<string, bool> media field name (without namespace) => accepts several items
     */
    private function mediaFields(string $formKey, string $namespace, string $locale): array
    {
        try {
            $metadata = $this->formMetadataProvider->getMetadata($formKey, $locale, []);
        } catch (\Throwable) {
            return [];
        }

        if (!$metadata instanceof FormMetadata) {
            return [];
        }

        $fields = [];
        foreach ($metadata->getFlatFieldMetadata() as $item) {
            $parts = \explode('/', $item->getName(), 2);
            if ($namespace !== $parts[0] || !isset($parts[1]) || !\in_array($item->getType(), self::MEDIA_TYPES, true)) {
                continue;
            }

            $fields[$parts[1]] = 'media_selection' === $item->getType();
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function missingLocales(ProductInterface $product, string $locale): array
    {
        $projectLocales = [];
        foreach ($this->webspaceManager->getWebspaceCollection()->getWebspaces() as $webspace) {
            foreach ($webspace->getAllLocalizations() as $localization) {
                $projectLocales[$localization->getLocale()] = true;
            }
        }

        $withContent = [$locale => true];
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if (DimensionContentInterface::STAGE_DRAFT !== $dimensionContent->getStage()) {
                continue;
            }

            if (null !== $dimensionContent->getLocale()) {
                $withContent[$dimensionContent->getLocale()] = true;
            }
            foreach ($dimensionContent->getAvailableLocales() ?? [] as $available) {
                $withContent[$available] = true;
            }
        }

        return \array_map('strval', \array_keys(\array_diff_key($projectLocales, $withContent)));
    }

    private static function mediaRecommendation(string $namespace, string $field, bool $multiple): string
    {
        return \sprintf(
            'No %s.%s. Pass %s.%s as %s with media ids from sulu_media_list or sulu_media_upload.',
            $namespace,
            $field,
            $namespace,
            $field,
            $multiple ? '{"ids": [<mediaId>, …]}' : '{"id": <mediaId>}',
        );
    }

    private static function isEmpty(mixed $value): bool
    {
        return null === $value || [] === $value || (\is_string($value) && '' === \trim($value));
    }

    private static function isEmptyMedia(mixed $value): bool
    {
        if (!\is_array($value)) {
            return self::isEmpty($value);
        }

        return self::isEmpty($value['id'] ?? null) && self::isEmpty($value['ids'] ?? null);
    }
}
