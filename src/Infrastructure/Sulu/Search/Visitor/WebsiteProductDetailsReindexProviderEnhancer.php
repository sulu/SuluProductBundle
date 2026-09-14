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

use CmsIg\Seal\Converter\HtmlToTextConverter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Indexes the details tab: the product family and the attribute values as filter fields of the
 * `product` object field, their text as searchable content, and the details image where neither
 * the template nor the excerpt has one.
 *
 * A variant carries its own values plus those of its parent that the family does not mark
 * variant-specific.
 *
 * @phpstan-type ValueRow array{
 *     productId: string,
 *     valueLocale: string|null,
 *     attributeKey: string,
 *     attributeType: string,
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
final class WebsiteProductDetailsReindexProviderEnhancer implements WebsiteProductReindexProviderEnhancerInterface
{
    public const FIELD = 'product';
    public const TEXT_VALUES_FIELD = 'attributes_text_values';
    public const NUMERIC_VALUES_FIELD = 'attributes_numeric_values';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * One entry of the text values field: the attribute an option key or text value belongs to is
     * part of the value, so text attributes need no field of their own.
     */
    public static function textValue(string $attributeKey, string $value): string
    {
        return self::sanitize($attributeKey) . ':' . $value;
    }

    public static function numericField(string $attributeKey): string
    {
        return self::sanitize($attributeKey);
    }

    /**
     * Search engines only accept word characters in field names, and a value of the text field
     * must stay parseable, so the attribute key is reduced to them in both.
     */
    private static function sanitize(string $key): string
    {
        return (string) \preg_replace('/[^A-Za-z0-9_]/', '_', $key);
    }

    public function enhanceQuery(QueryBuilder $queryBuilder): void
    {
        $queryBuilder
            ->leftJoin('unlocalizedDimensionContent.productFamily', 'productFamily')
            ->leftJoin('productFamily.translations', 'productFamilyTranslation', Join::WITH, 'productFamilyTranslation.locale = dimensionContent.locale')
            ->addSelect('unlocalizedDimensionContent.externalIdentifier')
            ->addSelect('productFamily.uuid AS productFamilyId')
            ->addSelect('productFamilyTranslation.name AS productFamilyName')
            ->addSelect('dimensionContent.detailsData')
            ->addSelect('unlocalizedDimensionContent.detailsData AS unlocalizedDetailsData');
    }

    public function enhanceDocument(array $queryResult, array $document): array
    {
        /** @var string $productId */
        $productId = $queryResult['productId'];
        /** @var string $locale */
        $locale = $queryResult['locale'];
        /** @var string $type */
        $type = $queryResult['type'];
        /** @var string|null $parentId */
        $parentId = $queryResult['parentId'];
        /** @var list<string> $content */
        $content = $document['content'];

        foreach (['externalIdentifier', 'productFamilyName'] as $key) {
            $value = $queryResult[$key] ?? null;
            if (\is_string($value) && '' !== $value) {
                $content[] = $value;
            }
        }

        // Details are split over both dimension contents by multilinguality; the localized ones win.
        $detailsData = \array_merge(
            \is_array($queryResult['unlocalizedDetailsData'] ?? null) ? $queryResult['unlocalizedDetailsData'] : [],
            \is_array($queryResult['detailsData'] ?? null) ? $queryResult['detailsData'] : [],
        );

        $shortDescription = $detailsData['shortDescription'] ?? null;
        if (\is_string($shortDescription)) {
            $text = HtmlToTextConverter::convert($shortDescription);
            if ('' !== $text) {
                $content[] = $text;
            }
        }

        $image = $detailsData['image'] ?? null;
        if ('' === ($document['mediaId'] ?? '') && \is_array($image) && isset($image['id']) && \is_numeric($image['id'])) {
            $document['mediaId'] = (string) $image['id'];
        }

        $textValues = [];
        $numericValues = [];

        $isVariant = ProductInterface::TYPE_VARIANT === $type && null !== $parentId;
        foreach ($this->loadValues($productId, $isVariant ? $parentId : null, $locale) as $key => $valueRow) {
            switch ($valueRow['attributeType']) {
                case AttributeInterface::TYPE_NUMBER:
                case AttributeInterface::TYPE_DATE:
                    if (null !== $valueRow['number']) {
                        $numericValues[self::numericField($key)] = [$valueRow['number']];
                    }
                    break;
                case AttributeInterface::TYPE_OPTIONS:
                    if (null !== $valueRow['optionKey']) {
                        $textValues[] = self::textValue($key, $valueRow['optionKey']);
                        $content[] = $valueRow['optionLabel'] ?? $valueRow['optionKey'];
                    }
                    break;
                case AttributeInterface::TYPE_TEXT:
                    $text = \is_string($valueRow['text']) ? \trim($valueRow['text']) : '';
                    if ('' !== $text) {
                        $textValues[] = self::textValue($key, $text);
                        $content[] = $text;
                    }
                    break;
            }
        }

        $productFamilyId = $queryResult['productFamilyId'] ?? null;

        $document['content'] = \array_values(\array_unique($content));
        $document[self::FIELD] = [
            'productFamilyId' => \is_string($productFamilyId) ? $productFamilyId : '',
            self::TEXT_VALUES_FIELD => \array_values(\array_unique($textValues)),
            self::NUMERIC_VALUES_FIELD => $numericValues,
        ];

        return $document;
    }

    /**
     * The product's own values win over the parent's; a variant-specific attribute of the parent is
     * not inherited.
     *
     * @return array<string, ValueRow> attribute key => row
     */
    private function loadValues(string $productId, ?string $parentId, string $locale): array
    {
        $values = [];
        foreach ($this->loadValueRows(\array_filter([$productId, $parentId]), $locale) as $valueRow) {
            // A value sits on the localized or the unlocalized row; should both carry it, the localized one wins.
            if (!isset($values[$valueRow['productId']][$valueRow['attributeKey']]) || null !== $valueRow['valueLocale']) {
                $values[$valueRow['productId']][$valueRow['attributeKey']] = $valueRow;
            }
        }

        $merged = $values[$productId] ?? [];
        foreach (null !== $parentId ? $values[$parentId] ?? [] : [] as $attributeKey => $valueRow) {
            if (true !== $valueRow['variantSpecific'] && !isset($merged[$attributeKey])) {
                $merged[$attributeKey] = $valueRow;
            }
        }

        return $merged;
    }

    /**
     * Live values in the current version, from the localized and the unlocalized dimension content.
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
            // The option relation of a value is not written, so its label is looked up by key.
            ->leftJoin('attribute.options', 'option', Join::WITH, 'option.key = value.attributeOptionKey')
            ->leftJoin('option.translations', 'optionTranslation', Join::WITH, 'optionTranslation.locale = :locale')
            ->leftJoin('value.productFamilyAttribute', 'familyAttribute')
            ->select('product.uuid AS productId')
            ->addSelect('dimensionContent.locale AS valueLocale')
            ->addSelect('attribute.key AS attributeKey')
            ->addSelect('attribute.type AS attributeType')
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
