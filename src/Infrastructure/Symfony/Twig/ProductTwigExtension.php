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

namespace Sulu\Product\Infrastructure\Symfony\Twig;

use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProductTwigExtension extends AbstractExtension
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentAggregatorInterface $contentAggregator,
        private RequestAnalyzerInterface $requestAnalyzer,
        private ReferenceStoreInterface $referenceStore,
        private ContentResolverInterface $contentResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sulu_product_load', [$this, 'loadProduct']),
        ];
    }

    /**
     * @param array<string, string> $properties
     *
     * @return array{attributes: list<array{key: string, label: string, type: string, value: mixed}>, ...}|null
     */
    public function loadProduct(
        string $uuid,
        array $properties,
        ?string $locale = null,
    ): ?array {
        if (null === $locale) {
            $localization = $this->requestAnalyzer->getCurrentLocalization();
            if (null === $localization) { // @phpstan-ignore identical.alwaysFalse
                return null;
            }
            $locale = $localization->getLocale();
        }

        $product = $this->productRepository->findOneBy(
            [
                'uuid' => $uuid,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
                ],
            ]
        );

        if (null === $product) {
            return null;
        }

        /** @var ProductDimensionContentInterface $dimensionContent */
        $dimensionContent = $this->contentAggregator->aggregate(
            $product,
            [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ]
        );

        $resolvedContent = $this->contentResolver->resolve($dimensionContent, $properties);
        $resolvedContent['attributes'] = $this->formatAttributes($dimensionContent, $locale);

        $this->referenceStore->add($product->getUuid(), ProductInterface::RESOURCE_KEY);

        return $resolvedContent;
    }

    /**
     * @return list<array{key: string, label: string, type: string, value: mixed}>
     */
    private function formatAttributes(ProductDimensionContentInterface $dimensionContent, string $locale): array
    {
        $result = [];

        foreach ($dimensionContent->getAttributes() as $productAttribute) {
            $attribute = $productAttribute->getAttribute();

            $value = match ($attribute->getType()) {
                AttributeInterface::TYPE_OPTIONS => $productAttribute->getAttributeOption()?->getTranslation($locale)?->getName()
                    ?? $productAttribute->getAttributeOptionKey(),
                AttributeInterface::TYPE_TEXT => $productAttribute->getText(),
                AttributeInterface::TYPE_NUMBER => $productAttribute->getNumber(),
                AttributeInterface::TYPE_DATE => $this->resolveDate($productAttribute->getNumber()),
                default => $productAttribute->getValue(),
            };

            $result[] = [
                'key' => $productAttribute->getAttributeKey(),
                'label' => $attribute->getTranslation($locale)?->getName() ?? $productAttribute->getAttributeKey(),
                'type' => $attribute->getType(),
                'value' => $value,
            ];
        }

        return $result;
    }

    private function resolveDate(?float $timestamp): ?string
    {
        if (null === $timestamp) {
            return null;
        }

        return (new \DateTimeImmutable('@' . (int) $timestamp))->format('Y-m-d');
    }
}
