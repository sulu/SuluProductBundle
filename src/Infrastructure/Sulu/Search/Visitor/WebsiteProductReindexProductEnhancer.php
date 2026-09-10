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
 * Fills the product field of the website document: option keys and text values as
 * "<attributeKey>:<value>" entries, number and date values per attribute field, and a display map.
 * The details tab's own text (code, external identifier, product family name, short description)
 * plus the text values and option labels go into the searchable content, which the content
 * enhancer resets, so this enhancer runs after it.
 *
 * A variant carries its own values plus those of its parent that the family does not mark
 * variant-specific. A product without variants carries its own.
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
final class WebsiteProductReindexProductEnhancer implements WebsiteProductReindexProviderEnhancerInterface, BatchAwareReindexEnhancerInterface
{
    /**
     * @var array<string, array<string, ValueRow>> a product's own values, keyed by "<productId>__<locale>" and attribute key
     */
    private array $ownValues = [];

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

        $productIds = [];
        $locales = [];
        foreach ($rows as $row) {
            $productId = $row['productId'] ?? null;
            if (!\is_string($productId)) {
                continue;
            }

            $productIds[] = $productId;

            // A variant reads its parent's values, so the parent is loaded with the batch.
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

        foreach (['code', 'externalIdentifier', 'productFamilyName'] as $key) {
            $value = $queryResult[$key] ?? null;
            if (\is_string($value) && '' !== $value) {
                $content[] = $value;
            }
        }

        $detailsData = $queryResult['detailsData'] ?? null;
        $shortDescription = \is_array($detailsData) ? $detailsData['shortDescription'] ?? null : null;
        if (\is_string($shortDescription)) {
            $text = \trim(\strip_tags($shortDescription));
            if ('' !== $text) {
                $content[] = $text;
            }
        }

        $textValues = [];
        $numericValues = [];
        $attributes = [];

        foreach ($this->mergedValues($productId, $locale, $type, \is_string($parentId) ? $parentId : null) as $key => $valueRow) {
            $label = $valueRow['attributeLabel'] ?? $key;

            switch ($valueRow['attributeType']) {
                case AttributeInterface::TYPE_NUMBER:
                case AttributeInterface::TYPE_DATE:
                    if (null === $valueRow['number']) {
                        break;
                    }

                    $numericValues[ProductIndex::numericField($key)] = [$valueRow['number']];
                    $attributes[$key] = ['label' => $label, 'value' => $valueRow['number']];
                    break;
                case AttributeInterface::TYPE_OPTIONS:
                    if (null === $valueRow['optionKey']) {
                        break;
                    }

                    $optionLabel = $valueRow['optionLabel'] ?? $valueRow['optionKey'];
                    $textValues[] = ProductIndex::textValue($key, $valueRow['optionKey']);
                    $content[] = $optionLabel;
                    $attributes[$key] = ['label' => $label, 'value' => $optionLabel];
                    break;
                case AttributeInterface::TYPE_TEXT:
                    $text = \is_string($valueRow['text']) ? \trim($valueRow['text']) : '';
                    if ('' === $text) {
                        break;
                    }

                    $textValues[] = ProductIndex::textValue($key, $text);
                    $content[] = $text;
                    $attributes[$key] = ['label' => $label, 'value' => $text];
                    break;
            }
        }

        $document['content'] = \array_values(\array_unique($content));

        /** @var array<string, mixed> $product */
        $product = \is_array($document[ProductIndex::FIELD] ?? null) ? $document[ProductIndex::FIELD] : [];
        $product[ProductIndex::TEXT_VALUES_FIELD] = \array_values(\array_unique($textValues));
        $product[ProductIndex::NUMERIC_VALUES_FIELD] = $numericValues;
        $product['attributes'] = $attributes;
        $document[ProductIndex::FIELD] = $product;

        return $document;
    }

    /**
     * Own values win over the parent's; a variant-specific attribute of the parent is not inherited.
     *
     * @return array<string, ValueRow> attribute key => row
     */
    private function mergedValues(string $productId, string $locale, string $type, ?string $parentId): array
    {
        $merged = $this->ownValues[$productId . '__' . $locale] ?? [];

        if (ProductInterface::TYPE_VARIANT === $type && null !== $parentId) {
            foreach ($this->ownValues[$parentId . '__' . $locale] ?? [] as $attributeKey => $valueRow) {
                if (true !== $valueRow['variantSpecific'] && !isset($merged[$attributeKey])) {
                    $merged[$attributeKey] = $valueRow;
                }
            }
        }

        return $merged;
    }

    /**
     * The values of the given products, in the live current version. A value sits on the localized
     * dimension content when its attribute is localized and on the unlocalized one otherwise, so
     * both rows are read for a document's locale.
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
            ->andWhere('product.uuid IN (:productIds)')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('locale', $locale)
            ->setParameter('productIds', $productIds)
            ->getQuery()
            ->getResult();
    }
}
