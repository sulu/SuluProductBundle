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

namespace Sulu\Product\Application\Ai;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Get the full technical specification sheet for one published product by its exact article
 * code. Framework-agnostic: Infrastructure\Symfony\Ai\Tool\GetProductDetailsTool wraps this for
 * symfony/ai-agent.
 */
final class GetProductDetails
{
    use ResolvesLiveProductContentTrait;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAttributeValueFormatter $valueFormatter,
        private readonly ProductUrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @return array{
     *     code: string,
     *     title: string,
     *     url: ?string,
     *     productFamily: ?string,
     *     specGroups: list<array{label: string, attributes: list<array{label: string, value: string}>}>,
     * }
     *
     * @throws \InvalidArgumentException when no published product has this code in this locale
     */
    public function __invoke(string $code, string $locale): array
    {
        try {
            $product = $this->productRepository->getOneBy([
                'code' => $code,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ProductNotFoundException) {
            throw new \InvalidArgumentException(\sprintf('No published product found with article code "%s".', $code));
        }

        $summary = $this->toProductSummary($product, $locale, $this->urlGenerator);
        [$localized, $unlocalized] = $this->findLiveDimensionContents($product, $locale);

        if (null === $summary || null === $localized || null === $unlocalized) {
            throw new \InvalidArgumentException(\sprintf('No published product found with article code "%s".', $code));
        }

        return [
            'code' => $summary['code'],
            'title' => $summary['title'],
            'url' => $summary['url'],
            'productFamily' => $summary['productFamily'],
            'specGroups' => $this->resolveSpecGroups($this->collectAttributeValues($product, $locale), $locale),
        ];
    }

    /**
     * A variant holds only its own attribute values, so the parent's are merged in underneath:
     * keyed by attribute key, the variant's value wins.
     *
     * @return list<ProductAttributeValueInterface>
     */
    private function collectAttributeValues(ProductInterface $product, string $locale): array
    {
        $parent = $product->getParent();
        $merged = [];

        foreach (null === $parent ? [$product] : [$parent, $product] as $source) {
            [$localized, $unlocalized] = $this->findLiveDimensionContents($source, $locale);

            foreach ([$unlocalized, $localized] as $dimensionContent) {
                foreach ($dimensionContent?->getAttributes() ?? [] as $value) {
                    $merged[$value->getAttribute()->getKey()] = $value;
                }
            }
        }

        return \array_values($merged);
    }

    /**
     * @param list<ProductAttributeValueInterface> $values
     *
     * @return list<array{label: string, attributes: list<array{label: string, value: string}>}>
     */
    private function resolveSpecGroups(array $values, string $locale): array
    {
        /** @var array<string, list<array{label: string, value: string}>> $byGroup */
        $byGroup = [];
        $groupOrder = [];

        foreach ($values as $value) {
            $attribute = $value->getAttribute();
            $display = $this->valueFormatter->format($value, $locale);

            if (null === $display || '' === \trim($display)) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupName = $group->getTranslation($locale)?->getName() ?? $group->getUuid() ?? '';
            $label = $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey();

            if (!isset($byGroup[$groupName])) {
                $byGroup[$groupName] = [];
                $groupOrder[] = $groupName;
            }

            $byGroup[$groupName][] = ['label' => $label, 'value' => \trim($display)];
        }

        $specGroups = [];
        foreach ($groupOrder as $groupName) {
            $specGroups[] = ['label' => $groupName, 'attributes' => $byGroup[$groupName]];
        }

        return $specGroups;
    }
}
