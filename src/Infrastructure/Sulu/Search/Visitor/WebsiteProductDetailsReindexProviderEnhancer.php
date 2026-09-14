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
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValue;

/**
 * Indexes the details tab: the product family and the attribute values as filter fields of the
 * `product` object field, their text as searchable content, and the details image where neither
 * the template nor the excerpt has one.
 *
 * A variant carries its own values plus those of its parent that the family does not mark
 * variant-specific.
 *
 * @phpstan-type ValueRow array{
 *     type: string,
 *     label: string|null,
 *     optionKey: string|null,
 *     optionLabel: string|null,
 *     number: float|null,
 *     text: string|null,
 *     config: string,
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

    private const UNIT_SEPARATOR = "\x1F";
    private const RECORD_SEPARATOR = "\x1E";
    private const FIELD_COUNT = 8;
    private const GROUP_CONCAT_MAX_LEN = 1048576;

    public function __construct(
        private readonly MeasurementRegistry $measurementRegistry,
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
        // MySQL and MariaDB cut GROUP_CONCAT at group_concat_max_len with only a warning, so the
        // session limit is raised for every batch; Postgres aggregates without a limit.
        $connection = $queryBuilder->getEntityManager()->getConnection();
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET SESSION group_concat_max_len = ' . self::GROUP_CONCAT_MAX_LEN);
        }

        $queryBuilder
            ->leftJoin('unlocalizedDimensionContent.productFamily', 'productFamily')
            ->leftJoin('productFamily.translations', 'productFamilyTranslation', Join::WITH, 'productFamilyTranslation.locale = dimensionContent.locale')
            ->addSelect('unlocalizedDimensionContent.externalIdentifier')
            ->addSelect('productFamily.uuid AS productFamilyId')
            ->addSelect('productFamilyTranslation.name AS productFamilyName')
            ->addSelect('dimensionContent.detailsData')
            ->addSelect('unlocalizedDimensionContent.detailsData AS unlocalizedDetailsData')
            ->addSelect('(' . $this->valuesSubquery($queryBuilder, 'own', 'product') . ') AS ownAttributeValues')
            ->addSelect('(' . $this->valuesSubquery($queryBuilder, 'parent', 'product.parent') . ') AS parentAttributeValues');
    }

    /**
     * The live values of one product, packed into one string so the batch query carries them and
     * no query runs per document. A localized value is ordered after the unlocalized one, so the
     * last record of an attribute wins. The parent's variant-specific attributes are not inherited.
     *
     * The product and its parent get a subquery each: with both in one `OR`, MySQL no longer uses
     * the product index and scans every dimension content of the locale per row.
     */
    private function valuesSubquery(QueryBuilder $queryBuilder, string $alias, string $productExpression): string
    {
        $u = "'" . self::UNIT_SEPARATOR . "'";
        $r = "'" . self::RECORD_SEPARATOR . "'";

        $subquery = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select(
                'GROUP_CONCAT('
                . "{$alias}Value.attributeKey, {$u}, "
                . "{$alias}Attribute.type, {$u}, "
                . "COALESCE({$alias}AttributeTranslation.name, {$alias}AttributeDefaultTranslation.name, ''), {$u}, "
                . "COALESCE({$alias}Value.attributeOptionKey, ''), {$u}, "
                . "COALESCE({$alias}OptionTranslation.name, {$alias}OptionDefaultTranslation.name, ''), {$u}, "
                . "COALESCE(CONCAT({$alias}Value.number, ''), ''), {$u}, "
                . "COALESCE({$alias}Value.text, ''), {$u}, "
                . "CAST({$alias}Attribute.config AS string), {$r}"
                . " ORDER BY CASE WHEN {$alias}DimensionContent.locale IS NULL THEN 0 ELSE 1 END ASC"
                . " SEPARATOR '')",
            )
            ->from(ProductAttributeValue::class, "{$alias}Value")
            ->innerJoin("{$alias}Value.productDimensionContent", "{$alias}DimensionContent")
            ->innerJoin("{$alias}Value.attribute", "{$alias}Attribute")
            // Labels fall back to the attribute's default locale, as the admin shows them.
            ->leftJoin("{$alias}Attribute.translations", "{$alias}AttributeTranslation", Join::WITH, "{$alias}AttributeTranslation.locale = dimensionContent.locale")
            ->leftJoin("{$alias}Attribute.translations", "{$alias}AttributeDefaultTranslation", Join::WITH, "{$alias}AttributeDefaultTranslation.locale = {$alias}Attribute.defaultLocale")
            // The option relation of a value is not written, so its label is looked up by key.
            ->leftJoin("{$alias}Attribute.options", "{$alias}Option", Join::WITH, "{$alias}Option.key = {$alias}Value.attributeOptionKey")
            ->leftJoin("{$alias}Option.translations", "{$alias}OptionTranslation", Join::WITH, "{$alias}OptionTranslation.locale = dimensionContent.locale")
            ->leftJoin("{$alias}Option.translations", "{$alias}OptionDefaultTranslation", Join::WITH, "{$alias}OptionDefaultTranslation.locale = {$alias}Attribute.defaultLocale")
            ->where("{$alias}DimensionContent.product = {$productExpression}")
            ->andWhere("{$alias}DimensionContent.stage = dimensionContent.stage")
            ->andWhere("{$alias}DimensionContent.version = dimensionContent.version")
            ->andWhere("{$alias}DimensionContent.locale = dimensionContent.locale OR {$alias}DimensionContent.locale IS NULL");

        if ('parent' === $alias) {
            $subquery
                ->leftJoin("{$alias}Value.productFamilyAttribute", "{$alias}FamilyAttribute")
                ->andWhere("{$alias}FamilyAttribute.variantSpecific = false OR {$alias}FamilyAttribute.variantSpecific IS NULL");
        }

        return $subquery->getDQL();
    }

    public function enhanceDocument(array $queryResult, array $document): array
    {
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

        $values = \array_replace(
            $this->decodeValues($queryResult['parentAttributeValues'] ?? null),
            $this->decodeValues($queryResult['ownAttributeValues'] ?? null),
        );
        foreach ($values as $key => $valueRow) {
            $display = null;
            switch ($valueRow['type']) {
                case AttributeInterface::TYPE_NUMBER:
                    if (null !== $valueRow['number']) {
                        $numericValues[self::numericField($key)] = [$valueRow['number']];
                        $display = \rtrim(\rtrim(\number_format($valueRow['number'], 10, '.', ''), '0'), '.');
                        $unit = $this->unitSymbol($valueRow['config']);
                        $display .= null !== $unit ? ' ' . $unit : '';
                    }
                    break;
                case AttributeInterface::TYPE_DATE:
                    if (null !== $valueRow['number']) {
                        $numericValues[self::numericField($key)] = [$valueRow['number']];
                        $display = (new \DateTimeImmutable('@' . (int) $valueRow['number']))->format(DateAttributeType::FORMAT);
                    }
                    break;
                case AttributeInterface::TYPE_OPTIONS:
                    if (null !== $valueRow['optionKey']) {
                        $textValues[] = self::textValue($key, $valueRow['optionKey']);
                        $display = $valueRow['optionLabel'] ?? $valueRow['optionKey'];
                    }
                    break;
                case AttributeInterface::TYPE_TEXT:
                    if (null !== $valueRow['text']) {
                        $textValues[] = self::textValue($key, $valueRow['text']);
                        $display = $valueRow['text'];
                    }
                    break;
            }

            if (null !== $display) {
                $content[] = ($valueRow['label'] ?? $key) . ': ' . $display;
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
     * @return array<string, ValueRow> attribute key => the winning row
     */
    private function decodeValues(mixed $packed): array
    {
        if (!\is_string($packed) || '' === $packed) {
            return [];
        }

        // Every complete record ends with the record separator, so a cut string is detected.
        if (!\str_ends_with($packed, self::RECORD_SEPARATOR)) {
            throw new \RuntimeException('The packed attribute values were truncated, raise the "group_concat_max_len" of the MySQL connection.');
        }

        $values = [];
        foreach (\explode(self::RECORD_SEPARATOR, \substr($packed, 0, -1)) as $record) {
            $fields = \explode(self::UNIT_SEPARATOR, $record);
            if (self::FIELD_COUNT !== \count($fields)) {
                throw new \RuntimeException('An attribute value contains a separator control character and cannot be indexed.');
            }

            [$key, $type, $label, $optionKey, $optionLabel, $number, $text, $config] = $fields;
            $text = \trim($text);

            $values[$key] = [
                'type' => $type,
                'label' => '' !== $label ? $label : null,
                'optionKey' => '' !== $optionKey ? $optionKey : null,
                'optionLabel' => '' !== $optionLabel ? $optionLabel : null,
                'number' => '' !== $number ? (float) $number : null,
                'text' => '' !== $text ? $text : null,
                'config' => $config,
            ];
        }

        return $values;
    }

    /**
     * @param string $config the attribute's config as JSON
     */
    private function unitSymbol(string $config): ?string
    {
        $decoded = \json_decode($config, true);
        $unitKey = \is_array($decoded) ? ($decoded['unit'] ?? null) : null;

        return \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey)?->getSymbol() : null;
    }
}
