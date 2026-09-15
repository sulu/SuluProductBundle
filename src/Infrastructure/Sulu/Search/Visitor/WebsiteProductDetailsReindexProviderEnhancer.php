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
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Indexes the details tab: family and attribute values as filter fields, their text as content,
 * and the details image as fallback. A variant also gets its parent's non-variant-specific values.
 *
 * Attributes and values are loaded once per run, so the query count does not grow with the
 * products; the cache lives until the kernel resets the service.
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
 *     labels: array<string, string>,
 *     defaultLocale: string|null,
 *     unit: string|null,
 *     options: array<string, array<string, string>>,
 * }
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own enhancer instead
 */
final class WebsiteProductDetailsReindexProviderEnhancer implements WebsiteProductReindexProviderEnhancerInterface, ResetInterface
{
    public const FIELD = 'product';
    public const TEXT_VALUES_FIELD = 'attributes_text_values';
    public const NUMERIC_VALUES_FIELD = 'attributes_numeric_values';

    private const UNLOCALIZED = '';

    /**
     * @var array<string, array<string, array<int, ValueRow>>>|null product id => locale => attribute id => row
     */
    private ?array $attributeValues = null;

    /**
     * @var array<int, AttributeDefinition>|null attribute id => definition
     */
    private ?array $attributeDefinitions = null;

    /**
     * @var list<string>|null the product ids the reindex is limited to, null for all
     */
    private ?array $scope = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MeasurementRegistry $measurementRegistry,
    ) {
    }

    /**
     * Prefixes the value with its attribute key, so text attributes share one field.
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
     * Reduces the key to word characters, the only ones search engines accept in field names.
     */
    private static function sanitize(string $key): string
    {
        return (string) \preg_replace('/[^A-Za-z0-9_]/', '_', $key);
    }

    public function reset(): void
    {
        $this->attributeValues = null;
        $this->attributeDefinitions = null;
        $this->scope = null;
    }

    public function enhanceQuery(QueryBuilder $queryBuilder): void
    {
        // The provider binds the reindexed product ids as :id<n>.
        $scope = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $value = $parameter->getValue();
            if (\is_string($value) && 1 === \preg_match('/^id\d+$/', $parameter->getName())) {
                $scope[] = $value;
            }
        }
        $scope = [] !== $scope ? \array_values(\array_unique($scope)) : null;
        if (null !== $scope) {
            \sort($scope);
        }
        if ($scope !== $this->scope) {
            $this->attributeValues = null;
            $this->scope = $scope;
        }

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

        // Localized details win over unlocalized ones.
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
        foreach ($this->mergedAttributeValues($productId, $isVariant ? $parentId : null, $locale) as $attributeId => $valueRow) {
            $attribute = $this->getAttributeDefinitions()[$attributeId] ?? null;
            if (null === $attribute) {
                continue;
            }

            $key = $attribute['key'];
            $display = null;
            switch ($attribute['type']) {
                case AttributeInterface::TYPE_NUMBER:
                    if (null !== $valueRow['number']) {
                        $numericValues[self::numericField($key)] = [$valueRow['number']];
                        $display = \rtrim(\rtrim(\number_format($valueRow['number'], 10, '.', ''), '0'), '.');
                        $display .= null !== $attribute['unit'] ? ' ' . $attribute['unit'] : '';
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
                        $display = $this->translate($attribute['options'][$valueRow['optionKey']] ?? [], $locale, $attribute['defaultLocale']) ?? $valueRow['optionKey'];
                    }
                    break;
                case AttributeInterface::TYPE_TEXT:
                    if (null !== $valueRow['text']) {
                        $textValues[] = self::textValue($key, $valueRow['text']);
                        $display = $valueRow['text'];
                    }
                    break;
            }

            // "Widerstand: 5 Ω", so both label and value are searchable.
            if (null !== $display) {
                $label = $this->translate($attribute['labels'], $locale, $attribute['defaultLocale']) ?? $key;
                $content[] = $label . ': ' . $display;
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
     * Own values win; the parent's variant-specific values are not inherited.
     *
     * @return array<int, ValueRow> attribute id => row
     */
    private function mergedAttributeValues(string $productId, ?string $parentId, string $locale): array
    {
        $merged = $this->productAttributeValues($productId, $locale);
        foreach (null !== $parentId ? $this->productAttributeValues($parentId, $locale) : [] as $attributeId => $valueRow) {
            if (!$valueRow['variantSpecific'] && !isset($merged[$attributeId])) {
                $merged[$attributeId] = $valueRow;
            }
        }

        return $merged;
    }

    /**
     * Localized values win over unlocalized ones.
     *
     * @return array<int, ValueRow> attribute id => row
     */
    private function productAttributeValues(string $productId, string $locale): array
    {
        $attributeValues = $this->getAttributeValues()[$productId] ?? [];

        return \array_replace($attributeValues[self::UNLOCALIZED] ?? [], $attributeValues[$locale] ?? []);
    }

    /**
     * Falls back to the attribute's default locale.
     *
     * @param array<string, string> $translations locale => text
     */
    private function translate(array $translations, string $locale, ?string $defaultLocale): ?string
    {
        return $translations[$locale] ?? (null !== $defaultLocale ? $translations[$defaultLocale] ?? null : null);
    }

    /**
     * Live values of all products, or of those in scope and their parents.
     *
     * @return array<string, array<string, array<int, ValueRow>>> product id => locale => attribute id => row
     */
    private function getAttributeValues(): array
    {
        if (null !== $this->attributeValues) {
            return $this->attributeValues;
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from(ProductAttributeValue::class, 'value')
            ->innerJoin('value.productDimensionContent', 'dimensionContent')
            ->leftJoin('value.productFamilyAttribute', 'familyAttribute')
            ->select('IDENTITY(dimensionContent.product) AS productId')
            ->addSelect('dimensionContent.locale')
            ->addSelect('IDENTITY(value.attribute) AS attributeId')
            ->addSelect('value.attributeOptionKey AS optionKey')
            ->addSelect('value.number')
            ->addSelect('value.text')
            ->addSelect('familyAttribute.variantSpecific')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version = :version')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION);

        if (null !== $this->scope) {
            $parents = $this->entityManager->createQueryBuilder()
                ->from(Product::class, 'variant')
                ->select('IDENTITY(variant.parent)')
                ->where('variant.uuid IN (:scope)')
                ->getDQL();
            $queryBuilder
                ->andWhere('dimensionContent.product IN (:scope) OR dimensionContent.product IN (' . $parents . ')')
                ->setParameter('scope', $this->scope);
        }

        /** @var iterable<array{productId: string, locale: string|null, attributeId: int, optionKey: string|null, number: float|null, text: string|null, variantSpecific: bool|null}> $rows */
        $rows = $queryBuilder->getQuery()->toIterable();

        $this->attributeValues = [];
        foreach ($rows as $row) {
            $text = \is_string($row['text']) ? \trim($row['text']) : '';
            $this->attributeValues[$row['productId']][$row['locale'] ?? self::UNLOCALIZED][(int) $row['attributeId']] = [
                'optionKey' => $row['optionKey'],
                'number' => $row['number'],
                'text' => '' !== $text ? $text : null,
                'variantSpecific' => true === $row['variantSpecific'],
            ];
        }

        return $this->attributeValues;
    }

    /**
     * All attributes with type, labels, options and unit.
     *
     * @return array<int, AttributeDefinition> attribute id => definition
     */
    private function getAttributeDefinitions(): array
    {
        if (null !== $this->attributeDefinitions) {
            return $this->attributeDefinitions;
        }

        /** @var list<array{attributeId: int, locale: string, name: string}> $labelRows */
        $labelRows = $this->entityManager->createQueryBuilder()
            ->from(Attribute::class, 'attribute')
            ->innerJoin('attribute.translations', 'translation')
            ->select('attribute.id AS attributeId', 'translation.locale', 'translation.name')
            ->getQuery()
            ->getArrayResult();
        $labels = [];
        foreach ($labelRows as $row) {
            $labels[$row['attributeId']][$row['locale']] = $row['name'];
        }

        /** @var list<array{attributeId: int, optionKey: string, locale: string, name: string}> $optionRows */
        $optionRows = $this->entityManager->createQueryBuilder()
            ->from(AttributeOption::class, 'option')
            ->innerJoin('option.translations', 'translation')
            ->select('IDENTITY(option.attribute) AS attributeId', 'option.key AS optionKey', 'translation.locale', 'translation.name')
            ->getQuery()
            ->getArrayResult();
        $options = [];
        foreach ($optionRows as $row) {
            $options[(int) $row['attributeId']][$row['optionKey']][$row['locale']] = $row['name'];
        }

        /** @var list<array{id: int, key: string, type: string, config: array<string, mixed>, defaultLocale: string|null}> $attributeRows */
        $attributeRows = $this->entityManager->createQueryBuilder()
            ->from(Attribute::class, 'attribute')
            ->select('attribute.id', 'attribute.key', 'attribute.type', 'attribute.config', 'attribute.defaultLocale')
            ->getQuery()
            ->getArrayResult();

        $this->attributeDefinitions = [];
        foreach ($attributeRows as $row) {
            $unitKey = $row['config']['unit'] ?? null;
            $this->attributeDefinitions[$row['id']] = [
                'key' => $row['key'],
                'type' => $row['type'],
                'labels' => $labels[$row['id']] ?? [],
                'defaultLocale' => $row['defaultLocale'],
                'unit' => \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey)?->getSymbol() : null,
                'options' => $options[$row['id']] ?? [],
            ];
        }

        return $this->attributeDefinitions;
    }
}
