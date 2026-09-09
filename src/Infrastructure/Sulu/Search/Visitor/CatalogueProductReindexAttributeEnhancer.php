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

namespace Sulu\Product\Infrastructure\Sulu\Search\Visitor;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

/**
 * Puts attribute values into the catalogue document: numbers and option keys into their
 * filter fields, text and option labels into the text bag, everything into the display map.
 * Values are merged between a parent and its variants by the family's variant-specific flag.
 *
 * @phpstan-type ValueRow array{
 *     productId: string,
 *     parentId: string|null,
 *     valueLocale: string|null,
 *     attributeKey: string,
 *     attributeType: string,
 *     attributeLabel: string|null,
 *     optionKey: string|null,
 *     optionLabel: string|null,
 *     number: float|null,
 *     text: string|null,
 *     variantSpecific: bool|null,
 * }
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own enhancer instead
 */
final class CatalogueProductReindexAttributeEnhancer implements WebsiteProductReindexProviderEnhancerInterface, BatchAwareReindexEnhancerInterface
{
    /**
     * @var array<string, array<string, ValueRow>> a product's own values, keyed by "<productId>__<locale>" and attribute key
     */
    private array $ownValues = [];

    /**
     * @var array<string, array<int, ValueRow>> the values of a product's variants, keyed by "<parentId>__<locale>"
     */
    private array $variantValues = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function enhanceQuery(QueryBuilder $queryBuilder): void
    {
    }

    public function prepareBatch(array $rows): void
    {
        $this->ownValues = [];
        $this->variantValues = [];

        $productIds = [];
        $locales = [];
        foreach ($rows as $row) {
            $productId = $row['productId'] ?? null;
            if (!\is_string($productId)) {
                continue;
            }

            $productIds[] = $productId;

            $parentId = $row['parentId'] ?? null;
            if (\is_string($parentId) && '' !== $parentId) {
                $productIds[] = $parentId;
            }

            $locale = $row['locale'] ?? null;
            if (\is_string($locale)) {
                $locales[$locale] = true;
            }
        }

        $productIds = \array_values(\array_unique($productIds));
        if ([] === $productIds) {
            return;
        }

        // Labels are translated per locale, so the values are loaded once per locale of the batch.
        foreach (\array_keys($locales) as $locale) {
            foreach ($this->loadValueRows($productIds, $locale) as $valueRow) {
                $key = $valueRow['productId'] . '__' . $locale;

                // The mapper writes a value either to the localized or to the unlocalized row.
                // Should both carry the same attribute, the localized one wins.
                if (!isset($this->ownValues[$key][$valueRow['attributeKey']]) || null !== $valueRow['valueLocale']) {
                    $this->ownValues[$key][$valueRow['attributeKey']] = $valueRow;
                }

                if (null !== $valueRow['parentId']) {
                    $this->variantValues[$valueRow['parentId'] . '__' . $locale][] = $valueRow;
                }
            }
        }
    }

    public function enhanceDocument(array $queryResult, array $document): array
    {
        $productId = $queryResult['productId'] ?? null;
        $locale = $queryResult['locale'] ?? null;
        $type = $queryResult['type'] ?? null;
        $parentId = $queryResult['parentId'] ?? null;

        if (!\is_string($productId) || !\is_string($locale) || !\is_string($type)) {
            return $document;
        }

        $content = [];
        foreach (\is_array($document['content'] ?? null) ? $document['content'] : [] as $text) {
            if (\is_string($text)) {
                $content[] = $text;
            }
        }

        $attributes = [];

        foreach ($this->mergedValues($productId, $locale, $type, \is_string($parentId) ? $parentId : null) as $key => $valueRows) {
            $label = $valueRows[0]['attributeLabel'] ?? $key;

            switch ($valueRows[0]['attributeType']) {
                case AttributeInterface::TYPE_NUMBER:
                case AttributeInterface::TYPE_DATE:
                    $numbers = [];
                    foreach ($valueRows as $valueRow) {
                        if (null !== $valueRow['number']) {
                            $numbers[] = $valueRow['number'];
                        }
                    }
                    $numbers = \array_values(\array_unique($numbers));
                    if ([] === $numbers) {
                        break;
                    }

                    $document[ProductIndex::attributeField($key)] = $numbers;
                    $attributes[$key] = ['label' => $label, 'value' => 1 === \count($numbers) ? $numbers[0] : $numbers];
                    break;
                case AttributeInterface::TYPE_OPTIONS:
                    $optionKeys = [];
                    $optionLabels = [];
                    foreach ($valueRows as $valueRow) {
                        if (null === $valueRow['optionKey']) {
                            continue;
                        }

                        $optionKeys[] = $valueRow['optionKey'];
                        $optionLabels[] = $valueRow['optionLabel'] ?? $valueRow['optionKey'];
                    }
                    $optionKeys = \array_values(\array_unique($optionKeys));
                    $optionLabels = \array_values(\array_unique($optionLabels));
                    if ([] === $optionKeys) {
                        break;
                    }

                    $document[ProductIndex::optionField($key)] = $optionKeys;
                    $content = \array_merge($content, $optionLabels);
                    $attributes[$key] = ['label' => $label, 'value' => 1 === \count($optionLabels) ? $optionLabels[0] : $optionLabels];
                    break;
                case AttributeInterface::TYPE_TEXT:
                    $texts = [];
                    foreach ($valueRows as $valueRow) {
                        if (\is_string($valueRow['text']) && '' !== \trim($valueRow['text'])) {
                            $texts[] = \trim($valueRow['text']);
                        }
                    }
                    $texts = \array_values(\array_unique($texts));
                    if ([] === $texts) {
                        break;
                    }

                    $content = \array_merge($content, $texts);
                    $attributes[$key] = ['label' => $label, 'value' => 1 === \count($texts) ? $texts[0] : $texts];
                    break;
            }
        }

        $document['content'] = \array_values(\array_unique($content));
        $document['attributes'] = $attributes;

        return $document;
    }

    /**
     * A product shows its own values, a parent additionally the variant-specific values of its
     * variants, a variant additionally the shared values of its parent. Own values win per key.
     *
     * @return array<string, array<int, ValueRow>> attribute key => rows
     */
    private function mergedValues(string $productId, string $locale, string $type, ?string $parentId): array
    {
        $merged = [];
        foreach ($this->ownValues[$productId . '__' . $locale] ?? [] as $attributeKey => $valueRow) {
            $merged[$attributeKey] = [$valueRow];
        }

        if (ProductInterface::TYPE_VARIANT === $type && null !== $parentId) {
            foreach ($this->ownValues[$parentId . '__' . $locale] ?? [] as $attributeKey => $valueRow) {
                if (true !== $valueRow['variantSpecific'] && !isset($merged[$attributeKey])) {
                    $merged[$attributeKey] = [$valueRow];
                }
            }
        }

        if (ProductInterface::TYPE_PRODUCT_WITH_VARIANTS === $type) {
            foreach ($this->variantValues[$productId . '__' . $locale] ?? [] as $valueRow) {
                if (true === $valueRow['variantSpecific']) {
                    $merged[$valueRow['attributeKey']][] = $valueRow;
                }
            }
        }

        return $merged;
    }

    /**
     * The values of the given products and of their variants, in the live current version.
     * A value sits on the localized dimension content when its attribute is localized and on the
     * unlocalized one otherwise, so both rows are read for a document's locale.
     *
     * @param string[] $productIds
     *
     * @return array<int, ValueRow>
     */
    private function loadValueRows(array $productIds, string $locale): array
    {
        /** @var array<int, ValueRow> */
        return $this->entityManager->createQueryBuilder()
            ->from(ProductAttributeValue::class, 'value')
            ->innerJoin('value.productDimensionContent', 'dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->innerJoin('value.attribute', 'attribute')
            ->leftJoin('attribute.translations', 'attributeTranslation', Join::WITH, 'attributeTranslation.locale = :locale')
            // The option relation of a value is not written, so its label is looked up by key.
            ->leftJoin('attribute.options', 'option', Join::WITH, 'option.key = value.attributeOptionKey')
            ->leftJoin('option.translations', 'optionTranslation', Join::WITH, 'optionTranslation.locale = :locale')
            ->leftJoin('value.productFamilyAttribute', 'familyAttribute')
            ->select('product.uuid AS productId')
            ->addSelect('IDENTITY(product.parent) AS parentId')
            ->addSelect('dimensionContent.locale AS valueLocale')
            ->addSelect('attribute.key AS attributeKey')
            ->addSelect('attribute.type AS attributeType')
            ->addSelect('attributeTranslation.name AS attributeLabel')
            ->addSelect('value.attributeOptionKey AS optionKey')
            ->addSelect('optionTranslation.name AS optionLabel')
            ->addSelect('value.number')
            ->addSelect('value.text')
            ->addSelect('familyAttribute.variantSpecific')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version = :version')
            ->andWhere('dimensionContent.locale = :locale OR dimensionContent.locale IS NULL')
            ->andWhere('product.uuid IN (:productIds) OR IDENTITY(product.parent) IN (:productIds)')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('locale', $locale)
            ->setParameter('productIds', $productIds)
            ->getQuery()
            ->getResult();
    }
}
