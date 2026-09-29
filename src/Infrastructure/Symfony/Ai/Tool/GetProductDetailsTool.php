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

namespace Sulu\Product\Infrastructure\Symfony\Ai\Tool;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_product_details',
    description: 'Get the full technical specification sheet for one published product by its exact article code. Use this for every specific spec value on a known product — never invent one. Use sulu_product_get_products first if the exact code is not known.',
)]
final class GetProductDetailsTool
{
    use ResolvesLiveProductContentTrait;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @param string $code Exact article code, e.g. "ABC-123". Use sulu_product_get_products
     *                     first if the exact code is not known.
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     *
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

        $summary = $this->toProductSummary($product, $locale);
        [$localized, $unlocalized] = $this->findLiveDimensionContents($product, $locale);

        if (null === $summary || null === $localized || null === $unlocalized) {
            throw new \InvalidArgumentException(\sprintf('No published product found with article code "%s".', $code));
        }

        return [
            'code' => $summary['code'],
            'title' => $summary['title'],
            'url' => $summary['url'],
            'productFamily' => $summary['productFamily'],
            'specGroups' => $this->resolveSpecGroups($localized, $unlocalized, $locale),
        ];
    }

    /**
     * @return list<array{label: string, attributes: list<array{label: string, value: string}>}>
     */
    private function resolveSpecGroups(
        ProductDimensionContentInterface $localized,
        ProductDimensionContentInterface $unlocalized,
        string $locale,
    ): array {
        /** @var array<string, list<array{label: string, value: string}>> $byGroup */
        $byGroup = [];
        $groupOrder = [];

        foreach ([...$unlocalized->getAttributes(), ...$localized->getAttributes()] as $value) {
            $attribute = $value->getAttribute();
            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $display = $this->displayAttributeValue($value, $type, $locale);

            if (null === $display) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupName = $group->getTranslation($locale)?->getName() ?? $group->getUuid() ?? '';
            $label = $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey();

            if (!isset($byGroup[$groupName])) {
                $byGroup[$groupName] = [];
                $groupOrder[] = $groupName;
            }

            $byGroup[$groupName][] = ['label' => $label, 'value' => $display];
        }

        $specGroups = [];
        foreach ($groupOrder as $groupName) {
            $specGroups[] = ['label' => $groupName, 'attributes' => $byGroup[$groupName]];
        }

        return $specGroups;
    }
}
