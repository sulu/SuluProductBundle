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
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductDetailsReindexProviderEnhancer;

#[CoversClass(WebsiteProductDetailsReindexProviderEnhancer::class)]
class WebsiteProductDetailsReindexProviderEnhancerTest extends TestCase
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
            [['id' => 1, 'key' => 'weight', 'type' => AttributeInterface::TYPE_NUMBER, 'config' => [], 'defaultLocale' => null]],
        );
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->createQueryBuilder()->willReturn($queryBuilder->reveal());

        $enhancer = new WebsiteProductDetailsReindexProviderEnhancer($entityManager->reveal(), new MeasurementRegistry());

        $document = $enhancer->enhanceDocument(
            ['productId' => 'product-1', 'locale' => 'en', 'type' => ProductInterface::TYPE_PRODUCT, 'parentId' => null],
            ['content' => [], 'mediaId' => ''],
        );

        $this->assertIsArray($document['product']);
        $this->assertSame(['weight' => [2.5]], $document['product']['attributes_numeric_values']);
    }

    public function testTextValueJoinsKeyAndValue(): void
    {
        $this->assertSame('colour:black', WebsiteProductDetailsReindexProviderEnhancer::textValue('colour', 'black'));
        $this->assertSame('cable_length:5 m', WebsiteProductDetailsReindexProviderEnhancer::textValue('cable-length', '5 m'));
    }

    public function testNumericFieldSanitisesKey(): void
    {
        $this->assertSame('cable_length', WebsiteProductDetailsReindexProviderEnhancer::numericField('cable_length'));
        $this->assertSame('cable_length_mm', WebsiteProductDetailsReindexProviderEnhancer::numericField('cable-length.mm'));
    }
}
