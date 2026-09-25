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
        $queryBuilder = $this->prophesize(QueryBuilder::class);
        foreach (['from', 'select', 'addSelect', 'innerJoin', 'leftJoin', 'where', 'andWhere', 'setParameter'] as $method) {
            $queryBuilder->$method(Argument::cetera())->willReturn($queryBuilder->reveal());
        }

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->toIterable()->willReturn([
            ['productId' => 'product-1', 'locale' => null, 'attributeId' => 1, 'optionKey' => null, 'number' => 2.5, 'text' => null, 'variantSpecific' => false],
            ['productId' => 'product-1', 'locale' => null, 'attributeId' => 2, 'optionKey' => null, 'number' => 7.0, 'text' => null, 'variantSpecific' => false],
        ]);
        // Labels, options, attributes: only attribute 1 is known.
        $query->getArrayResult()->willReturn(
            [],
            [],
            [['id' => 1, 'key' => 'weight', 'type' => AttributeInterface::TYPE_NUMBER, 'filterable' => true, 'config' => [], 'defaultLocale' => null]],
        );
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

        $document = $enhancer->enhanceDocument(
            ['productId' => 'product-1', 'locale' => 'en', 'parentId' => null],
            ['content' => [], 'mediaId' => ''],
        );

        $this->assertIsArray($document['product']);
        $this->assertSame(['weight' => [2.5]], $document['product']['attributes_numeric_values']);
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
