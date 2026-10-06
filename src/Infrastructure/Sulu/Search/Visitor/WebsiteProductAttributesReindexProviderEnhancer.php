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
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\AttributeType\BooleanAttributeType;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Filter fields and attribute text of a product; definitions load once per run, values per batch.
 *
 * @phpstan-type ValueRow array{
 *     optionKey: string|null,
 *     number: float|null,
 *     text: string|null,
 *     variantSpecific: bool,
 * }
 * @phpstan-type AttributeDefinition array{
 *     key: string,
 *     type: string,
 *     filterable: bool,
 *     labels: array<string, string>,
 *     defaultLocale: string|null,
 *     unit: string|null,
 *     options: array<string, array<string, string>>,
 * }
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own enhancer instead
 */
final class WebsiteProductAttributesReindexProviderEnhancer implements WebsiteProductReindexProviderEnhancerInterface, ResetInterface
{
    public const FIELD = 'product';
    public const PRODUCT_FAMILY_KEY_FIELD = 'productFamilyKey';
    public const TEXT_VALUES_FIELD = 'attributes_text_values';
    public const NUMERIC_VALUES_FIELD = 'attributes_numeric_values';

    private const UNLOCALIZED = '';

    /**
     * @var array<string, array<string, array<string, ValueRow>>> product id => locale => attribute uuid => row
     */
    private array $attributeValues = [];

    /**
     * @var array<string, AttributeDefinition>|null attribute uuid => definition
     */
    private ?array $attributeDefinitions = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MeasurementRegistry $measurementRegistry,
    ) {
    }

    /**
     * Prefixes the value with its attribute key, so options and boolean attributes share one field.
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
     * Word characters starting with a letter, the only field names every engine accepts.
     */
    private static function sanitize(string $key): string
    {
        $name = (string) \preg_replace('/[^A-Za-z0-9_]/', '_', $key);

        return 1 === \preg_match('/^[A-Za-z]/', $name) ? $name : 'a_' . $name;
    }

    public function reset(): void
    {
        $this->attributeValues = [];
        $this->attributeDefinitions = null;
    }

    public function enhanceQuery(QueryBuilder $queryBuilder): void
    {
        $this->attributeValues = $this->loadAttributeValues($this->loadBatchProductIds($queryBuilder));
    }

    public function enhanceDocument(array $queryResult, array $document): array
    {
        /** @var string $productId */
        $productId = $queryResult['productId'];
        /** @var string $locale */
        $locale = $queryResult['locale'];
        /** @var string|null $parentId */
        $parentId = $queryResult['parentId'];
        /** @var list<string> $content */
        $content = $document['content'];

        $textValues = [];
        $numericValues = [];

        foreach ($this->mergedAttributeValues($productId, $parentId, $locale) as $attributeUuid => $valueRow) {
            $attribute = $this->getAttributeDefinitions()[$attributeUuid] ?? null;
            if (null === $attribute) {
                continue;
            }

            $key = $attribute['key'];
            $filterable = $attribute['filterable'];
            $display = null;
            switch ($attribute['type']) {
                case AttributeInterface::TYPE_NUMBER:
                    if (null !== $valueRow['number']) {
                        if ($filterable) {
                            $numericValues[self::numericField($key)] = [$valueRow['number']];
                        }
                        $display = \rtrim(\rtrim(\number_format($valueRow['number'], 10, '.', ''), '0'), '.');
                        $display .= null !== $attribute['unit'] ? ' ' . $attribute['unit'] : '';
                    }
                    break;
                case AttributeInterface::TYPE_DATE:
                    if (null !== $valueRow['number']) {
                        if ($filterable) {
                            $numericValues[self::numericField($key)] = [$valueRow['number']];
                        }
                        $display = (new \DateTimeImmutable('@' . (int) $valueRow['number']))->format(DateAttributeType::FORMAT);
                    }
                    break;
                case AttributeInterface::TYPE_OPTIONS:
                    if (null !== $valueRow['optionKey']) {
                        if ($filterable) {
                            $textValues[] = self::textValue($key, $valueRow['optionKey']);
                        }
                        $display = $this->translate($attribute['options'][$valueRow['optionKey']] ?? [], $locale, $attribute['defaultLocale']) ?? $valueRow['optionKey'];
                    }
                    break;
                case AttributeInterface::TYPE_TEXT:
                    $display = $valueRow['text'];
                    break;
                case AttributeInterface::TYPE_BOOLEAN:
                    if (null !== $valueRow['number']) {
                        $isTrue = 0.0 !== $valueRow['number'];
                        if ($filterable) {
                            $textValues[] = self::textValue($key, $isTrue ? BooleanAttributeType::TRUE : BooleanAttributeType::FALSE);
                        }
                        // Only yes adds the label, so a search for it does not find the products answered with no.
                        if ($isTrue) {
                            $content[] = $this->translate($attribute['labels'], $locale, $attribute['defaultLocale']) ?? $key;
                        }
                    }
                    break;
            }

            if (null !== $display) {
                $label = $this->translate($attribute['labels'], $locale, $attribute['defaultLocale']) ?? $key;
                $content[] = $label . ': ' . $display;
            }
        }

        // Selected by the details enhancer, which always runs.
        $productFamilyKey = $queryResult['productFamilyKey'] ?? null;

        $document['content'] = \array_values(\array_unique($content));
        $document[self::FIELD] = [
            self::PRODUCT_FAMILY_KEY_FIELD => \is_string($productFamilyKey) ? $productFamilyKey : '',
            self::TEXT_VALUES_FIELD => \array_values(\array_unique($textValues)),
            self::NUMERIC_VALUES_FIELD => $numericValues,
        ];

        return $document;
    }

    /**
     * @return array<string, ValueRow> attribute uuid => row
     */
    private function mergedAttributeValues(string $productId, ?string $parentId, string $locale): array
    {
        $merged = $this->productAttributeValues($productId, $locale);
        foreach (null !== $parentId ? $this->productAttributeValues($parentId, $locale) : [] as $attributeUuid => $valueRow) {
            if (!$valueRow['variantSpecific'] && !isset($merged[$attributeUuid])) {
                $merged[$attributeUuid] = $valueRow;
            }
        }

        return $merged;
    }

    /**
     * @return array<string, ValueRow> attribute uuid => row
     */
    private function productAttributeValues(string $productId, string $locale): array
    {
        $attributeValues = $this->attributeValues[$productId] ?? [];

        return \array_replace($attributeValues[self::UNLOCALIZED] ?? [], $attributeValues[$locale] ?? []);
    }

    /**
     * @param array<string, string> $translations locale => text
     */
    private function translate(array $translations, string $locale, ?string $defaultLocale): ?string
    {
        return $translations[$locale] ?? (null !== $defaultLocale ? $translations[$defaultLocale] ?? null : null);
    }

    /**
     * The batch's products and their parents; the provider limits the query to the batch.
     *
     * @return list<string>
     */
    private function loadBatchProductIds(QueryBuilder $queryBuilder): array
    {
        /** @var list<array{productId: string, parentId: string|null}> $rows */
        $rows = (clone $queryBuilder)
            ->select('product.uuid AS productId', 'IDENTITY(product.parent) AS parentId')
            ->getQuery()
            ->getArrayResult();

        return \array_values(\array_unique(\array_filter(\array_merge(
            \array_column($rows, 'productId'),
            \array_column($rows, 'parentId'),
        ))));
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, array<string, array<string, ValueRow>>> product id => locale => attribute uuid => row
     */
    private function loadAttributeValues(array $productIds): array
    {
        /** @var iterable<array{productId: string, locale: string|null, attributeUuid: string, optionKey: string|null, number: float|null, text: string|null, variantSpecific: bool|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->from(ProductAttributeValue::class, 'value')
            ->innerJoin('value.productDimensionContent', 'dimensionContent')
            ->leftJoin('value.productFamilyAttribute', 'familyAttribute')
            ->leftJoin('value.attributeOption', 'attributeOption')
            ->select('IDENTITY(dimensionContent.product) AS productId')
            ->addSelect('dimensionContent.locale')
            ->addSelect('IDENTITY(value.attribute) AS attributeUuid')
            ->addSelect('attributeOption.key AS optionKey')
            ->addSelect('value.number')
            ->addSelect('value.text')
            ->addSelect('familyAttribute.variantSpecific')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version = :version')
            ->andWhere('dimensionContent.product IN (:productIds)')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->setParameter('productIds', $productIds)
            ->getQuery()
            ->toIterable();

        $attributeValues = [];
        foreach ($rows as $row) {
            $text = \is_string($row['text']) ? \trim($row['text']) : '';
            $attributeValues[$row['productId']][$row['locale'] ?? self::UNLOCALIZED][$row['attributeUuid']] = [
                'optionKey' => $row['optionKey'],
                'number' => $row['number'],
                'text' => '' !== $text ? $text : null,
                'variantSpecific' => true === $row['variantSpecific'],
            ];
        }

        return $attributeValues;
    }

    /**
     * @return array<string, AttributeDefinition> attribute uuid => definition
     */
    private function getAttributeDefinitions(): array
    {
        if (null !== $this->attributeDefinitions) {
            return $this->attributeDefinitions;
        }

        /** @var list<array{attributeUuid: string, locale: string, name: string}> $labelRows */
        $labelRows = $this->entityManager->createQueryBuilder()
            ->from(Attribute::class, 'attribute')
            ->innerJoin('attribute.translations', 'translation')
            ->select('attribute.uuid AS attributeUuid', 'translation.locale', 'translation.name')
            ->getQuery()
            ->getArrayResult();
        $labels = [];
        foreach ($labelRows as $row) {
            $labels[$row['attributeUuid']][$row['locale']] = $row['name'];
        }

        /** @var list<array{attributeUuid: string, optionKey: string, locale: string, name: string}> $optionRows */
        $optionRows = $this->entityManager->createQueryBuilder()
            ->from(AttributeOption::class, 'option')
            ->innerJoin('option.translations', 'translation')
            ->select('IDENTITY(option.attribute) AS attributeUuid', 'option.key AS optionKey', 'translation.locale', 'translation.name')
            ->getQuery()
            ->getArrayResult();
        $options = [];
        foreach ($optionRows as $row) {
            $options[$row['attributeUuid']][$row['optionKey']][$row['locale']] = $row['name'];
        }

        /** @var list<array{uuid: string, key: string, type: string, filterable: bool, config: array<string, mixed>, defaultLocale: string|null}> $attributeRows */
        $attributeRows = $this->entityManager->createQueryBuilder()
            ->from(Attribute::class, 'attribute')
            ->select('attribute.uuid', 'attribute.key', 'attribute.type', 'attribute.filterable', 'attribute.config', 'attribute.defaultLocale')
            ->getQuery()
            ->getArrayResult();

        $this->attributeDefinitions = [];
        foreach ($attributeRows as $row) {
            $unitKey = $row['config']['unit'] ?? null;
            $this->attributeDefinitions[$row['uuid']] = [
                'key' => $row['key'],
                'type' => $row['type'],
                'filterable' => $row['filterable'],
                'labels' => $labels[$row['uuid']] ?? [],
                'defaultLocale' => $row['defaultLocale'],
                'unit' => \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey)?->getSymbol() : null,
                'options' => $options[$row['uuid']] ?? [],
            ];
        }

        return $this->attributeDefinitions;
    }
}
