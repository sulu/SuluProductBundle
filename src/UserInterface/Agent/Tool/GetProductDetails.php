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

namespace Sulu\Product\UserInterface\Agent\Tool;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Attribute\AttributeValueConverterInterface;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_product_details',
    description: 'Get the full technical specification sheet for one published product by its exact article code. Use this for every specific spec value on a known product. Never invent one. Use sulu_product_get_products first if the exact code is not known.',
)]
final class GetProductDetails
{
    use ResolvesLiveProductContentTrait;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAttributeValueFormatter $valueFormatter,
        private readonly AttributeValueConverterInterface $attributeValueConverter,
        private readonly ProductUrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @param string $code exact article code, e.g. "ABC-123", use sulu_product_get_products first if it is not known
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     *
     * @return array{
     *     status: 'ok'|'not_found',
     *     instruction: ?string,
     *     code: string,
     *     title: string,
     *     url: ?string,
     *     productFamily: ?string,
     *     specGroups: list<array{label: string, attributes: list<array{label: string, value: string}>}>,
     * }
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
            return $this->notFound($code);
        }

        $summary = $this->toProductSummary($product, $locale, $this->urlGenerator);
        [$localized, $unlocalized] = $this->findLiveDimensionContents($product, $locale);

        if (null === $summary || null === $localized || null === $unlocalized) {
            return $this->notFound($code);
        }

        return [
            'status' => 'ok',
            'instruction' => null,
            'code' => $summary['code'],
            'title' => $summary['title'],
            'url' => $summary['url'],
            'productFamily' => $summary['productFamily'],
            'specGroups' => $this->resolveSpecGroups($this->collectAttributeValues($product, $locale), $locale),
        ];
    }

    /**
     * @return array{status: 'not_found', instruction: string, code: string, title: string, url: null, productFamily: null, specGroups: list<never>}
     */
    private function notFound(string $code): array
    {
        return [
            'status' => 'not_found',
            'instruction' => \sprintf(
                'No published product found with article code "%s". Do not guess or construct a code. '
                . 'Look up the exact code first, or tell the visitor plainly that the product was not found.',
                $code,
            ),
            'code' => $code,
            'title' => '',
            'url' => null,
            'productFamily' => null,
            'specGroups' => [],
        ];
    }

    /**
     * A variant holds only its own attribute values, so the parent's are merged in underneath:
     * keyed by attribute key, the variant's value wins.
     *
     * @return list<array{attribute: AttributeInterface, value: mixed}>
     */
    private function collectAttributeValues(ProductInterface $product, string $locale): array
    {
        $parent = $product->getParent();
        $merged = [];

        foreach (null === $parent ? [$product] : [$parent, $product] as $source) {
            [$localized, $unlocalized] = $this->findLiveDimensionContents($source, $locale);

            foreach ([$unlocalized, $localized] as $dimensionContent) {
                foreach ($this->attributeValueConverter->normalizeAll($dimensionContent?->getAttributes() ?? []) as $attributeValue) {
                    $merged[$attributeValue['attribute']->getKey()] = $attributeValue;
                }
            }
        }

        return \array_values($merged);
    }

    /**
     * @param list<array{attribute: AttributeInterface, value: mixed}> $values
     *
     * @return list<array{label: string, attributes: list<array{label: string, value: string}>}>
     */
    private function resolveSpecGroups(array $values, string $locale): array
    {
        /** @var array<string, list<array{label: string, value: string}>> $byGroup */
        $byGroup = [];
        $groupOrder = [];

        foreach ($values as ['attribute' => $attribute, 'value' => $value]) {
            $display = $this->valueFormatter->format($attribute, $value, $locale);
            if (\is_bool($display)) {
                $display = $display ? 'true' : 'false';
            }

            if (null === $display || '' === \trim($display)) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupName = $group->getTranslation($locale)?->getName() ?? $group->getUuid();
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
