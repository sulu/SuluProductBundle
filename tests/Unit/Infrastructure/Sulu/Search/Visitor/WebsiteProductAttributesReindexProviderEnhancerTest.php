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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search\Visitor;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductAttributesReindexProviderEnhancer;

#[CoversClass(WebsiteProductAttributesReindexProviderEnhancer::class)]
class WebsiteProductAttributesReindexProviderEnhancerTest extends TestCase
{
    use ProphecyTrait;

    public function testValueOfAnUnknownAttributeIsSkipped(): void
    {
        $document = $this->enhanceDocument(
            [
                $this->valueRow('attribute-1', 'value', 2.5),
                $this->valueRow('attribute-2', 'value', 7.0),
            ],
            // Only attribute 1 is known.
            [['uuid' => 'attribute-1', 'key' => 'weight', 'type' => AttributeInterface::TYPE_NUMBER, 'filterable' => true, 'config' => [], 'defaultLocale' => null]],
        );

        $this->assertIsArray($document['product']);
        $this->assertSame(['weight' => [2.5]], $document['product']['attributes_numeric_values']);
    }

    /**
     * @return iterable<string, array{float|null, float|null, string|null, string|null}>
     */
    public static function rangeContentProvider(): iterable
    {
        yield 'both bounds' => [-20.0, 60.0, null, 'Temperature: -20 – 60'];
        yield 'decimals' => [1.5, 4.25, null, 'Temperature: 1.5 – 4.25'];
        yield 'from only' => [5.0, null, null, 'Temperature: ≥ 5'];
        yield 'to only' => [null, 7.5, null, 'Temperature: ≤ 7.5'];
        yield 'both bounds with a unit' => [-20.0, 60.0, 'CELSIUS', 'Temperature: -20 – 60 °C'];
        yield 'from only with a unit' => [5.0, null, 'CELSIUS', 'Temperature: ≥ 5 °C'];
        yield 'to only with a unit' => [null, 7.5, 'CELSIUS', 'Temperature: ≤ 7.5 °C'];
        yield 'no bound' => [null, null, 'CELSIUS', null];
    }

    #[DataProvider('rangeContentProvider')]
    public function testRangeAddsItsLabelAndBoundsToTheContent(?float $from, ?float $to, ?string $unit, ?string $expected): void
    {
        $document = $this->enhanceDocument(
            [
                $this->valueRow('range-1', 'from', $from),
                $this->valueRow('range-1', 'to', $to),
            ],
            [['uuid' => 'range-1', 'key' => 'temperature', 'type' => AttributeInterface::TYPE_RANGE, 'filterable' => false, 'config' => null === $unit ? [] : ['unit' => $unit], 'defaultLocale' => null]],
            [['attributeUuid' => 'range-1', 'locale' => 'en', 'name' => 'Temperature']],
        );

        $this->assertSame(null === $expected ? [] : [$expected], $document['content']);
    }

    public function testRangeWithOnlyOneStoredRowIsOpenOnTheOtherSide(): void
    {
        $document = $this->enhanceDocument(
            [$this->valueRow('range-1', 'to', 9.0)],
            [['uuid' => 'range-1', 'key' => 'temperature', 'type' => AttributeInterface::TYPE_RANGE, 'filterable' => false, 'config' => [], 'defaultLocale' => null]],
        );

        $this->assertSame(['temperature: ≤ 9'], $document['content'], 'Without a translated label the key is used.');
    }

    public function testAFilterableRangeAddsNoFilterField(): void
    {
        $document = $this->enhanceDocument(
            [
                $this->valueRow('range-1', 'from', -20.0),
                $this->valueRow('range-1', 'to', 60.0),
                $this->valueRow('number-1', 'value', 2.5),
            ],
            [
                ['uuid' => 'range-1', 'key' => 'temperature', 'type' => AttributeInterface::TYPE_RANGE, 'filterable' => true, 'config' => [], 'defaultLocale' => null],
                ['uuid' => 'number-1', 'key' => 'weight', 'type' => AttributeInterface::TYPE_NUMBER, 'filterable' => true, 'config' => [], 'defaultLocale' => null],
            ],
        );

        $this->assertIsArray($document['product']);
        $this->assertSame(['weight' => [2.5]], $document['product']['attributes_numeric_values']);
        $this->assertSame([], $document['product']['attributes_text_values']);
        $this->assertSame(['temperature: -20 – 60', 'weight: 2.5'], $document['content']);
    }

    public function testANumberKeepsItsFilterFieldAndContent(): void
    {
        $document = $this->enhanceDocument(
            [$this->valueRow('number-1', 'value', 5.0)],
            [['uuid' => 'number-1', 'key' => 'resistance', 'type' => AttributeInterface::TYPE_NUMBER, 'filterable' => true, 'config' => ['unit' => 'OHM'], 'defaultLocale' => null]],
            [['attributeUuid' => 'number-1', 'locale' => 'en', 'name' => 'Resistance']],
        );

        $this->assertIsArray($document['product']);
        $this->assertSame(['resistance' => [5.0]], $document['product']['attributes_numeric_values']);
        $this->assertSame(['Resistance: 5 Ω'], $document['content']);
    }

    /**
     * @return array{productId: string, locale: string|null, attributeUuid: string, valueKey: string, optionKey: string|null, number: float|null, text: string|null, variantSpecific: bool}
     */
    private function valueRow(string $attributeUuid, string $valueKey, ?float $number): array
    {
        return ['productId' => 'product-1', 'locale' => null, 'attributeUuid' => $attributeUuid, 'valueKey' => $valueKey, 'optionKey' => null, 'number' => $number, 'text' => null, 'variantSpecific' => false];
    }

    /**
     * Runs the enhancer for product-1 on an empty document, with the database answering the given rows.
     *
     * @param list<array<string, mixed>> $valueRows the attribute values of product-1
     * @param list<array<string, mixed>> $attributeRows the known attributes
     * @param list<array<string, mixed>> $labelRows the attribute names
     *
     * @return array<string, mixed>
     */
    private function enhanceDocument(array $valueRows, array $attributeRows, array $labelRows = []): array
    {
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        foreach (['from', 'select', 'addSelect', 'innerJoin', 'leftJoin', 'where', 'andWhere', 'setParameter'] as $method) {
            $queryBuilder->$method(Argument::cetera())->willReturn($queryBuilder->reveal());
        }

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->toIterable()->willReturn($valueRows);
        // Labels, options, attributes.
        $query->getArrayResult()->willReturn($labelRows, [], $attributeRows);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->createQueryBuilder()->willReturn($queryBuilder->reveal());

        // The provider's batch query, which the enhancer clones to read the batch's product ids.
        /** @var ObjectProphecy<Query<mixed, mixed>> $batchQuery */
        $batchQuery = $this->prophesize(Query::class);
        foreach (['setParameters', 'setFirstResult', 'setMaxResults'] as $method) {
            $batchQuery->$method(Argument::cetera())->willReturn($batchQuery->reveal());
        }
        $batchQuery->getArrayResult()->willReturn([['productId' => 'product-1', 'parentId' => null]]);
        $entityManager->createQuery(Argument::type('string'))->willReturn($batchQuery->reveal());

        $enhancer = new WebsiteProductAttributesReindexProviderEnhancer($entityManager->reveal(), new MeasurementRegistry());
        $enhancer->enhanceQuery((new QueryBuilder($entityManager->reveal()))->from(ProductDimensionContent::class, 'dimensionContent'));

        return $enhancer->enhanceDocument(
            ['productId' => 'product-1', 'locale' => 'en', 'parentId' => null],
            ['content' => [], 'mediaId' => ''],
        );
    }

    public function testTextValueJoinsKeyAndValue(): void
    {
        $this->assertSame('colour:black', WebsiteProductAttributesReindexProviderEnhancer::textValue('colour', 'black'));
        $this->assertSame('cable_length:5 m', WebsiteProductAttributesReindexProviderEnhancer::textValue('cable-length', '5 m'));
    }

    public function testNumericFieldSanitisesKey(): void
    {
        $this->assertSame('cable_length', WebsiteProductAttributesReindexProviderEnhancer::numericField('cable_length'));
        $this->assertSame('cable_length_mm', WebsiteProductAttributesReindexProviderEnhancer::numericField('cable-length.mm'));
        $this->assertSame('a_3d_depth', WebsiteProductAttributesReindexProviderEnhancer::numericField('3d_depth'), 'A field name starts with a letter.');
        $this->assertSame('a__depth', WebsiteProductAttributesReindexProviderEnhancer::numericField('_depth'));
    }
}
