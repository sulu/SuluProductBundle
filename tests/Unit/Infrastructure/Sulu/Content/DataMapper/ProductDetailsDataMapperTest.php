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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\DataMapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductDetailsDataMapper;

#[CoversClass(ProductDetailsDataMapper::class)]
class ProductDetailsDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    private ProductDetailsDataMapper $mapper;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->mapper = new ProductDetailsDataMapper($this->productFamilyRepository->reveal());
    }

    private function makeDimensionContent(): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product(new ProductFamily()));
    }

    public function testEarlyReturnWhenNotProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($other->reveal(), $other->reveal(), ['title' => 'X']);

        $this->addToAssertionCount(1);
    }

    public function testSetsTitle(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['title' => 'Hello']);

        $this->assertSame('Hello', $loc->getTitle());
    }

    public function testSetsTitleToNull(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setTitle('old');

        $this->mapper->map($unloc, $loc, ['title' => null]);

        $this->assertNull($loc->getTitle());
    }

    public function testSetsCode(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['code' => 'SKU-001']);

        $this->assertSame('SKU-001', $unloc->getCode());
    }

    public function testSetsExternalIdentifier(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['externalIdentifier' => 'EXT-42']);

        $this->assertSame('EXT-42', $unloc->getExternalIdentifier());
    }

    public function testSetsProductFamily(): void
    {
        $family = new ProductFamily();
        $family->setUuid('fam-uuid');

        /** @var ObjectProphecy<ProductFamilyInterface> $familyMock */
        $familyMock = $this->prophesize(ProductFamilyInterface::class);
        $this->productFamilyRepository->getOneBy(['uuid' => 'fam-uuid'])
            ->willReturn($familyMock->reveal());

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['productFamily' => 'fam-uuid']);

        $this->assertSame($familyMock->reveal(), $unloc->getProductFamily());
    }

    public function testSkipsProductFamilyWhenNull(): void
    {
        $this->productFamilyRepository->getOneBy()->shouldNotBeCalled();

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $unloc->setCode('SKU');

        $this->mapper->map($unloc, $loc, ['productFamily' => null]);

        $this->assertSame('SKU', $unloc->getCode());
    }

    public function testNoOpWhenNoKnownKeys(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['locale' => 'en', 'template' => 'product']);

        $this->assertNull($loc->getTitle());
        $this->assertNull($unloc->getCode());
    }

    public function testTitleSkippedWhenLocalizedIsNotProductDimensionContent(): void
    {
        $unloc = $this->makeDimensionContent();
        $locOther = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($unloc, $locOther->reveal(), ['title' => 'X', 'code' => 'SKU-2']);

        $this->assertSame('SKU-2', $unloc->getCode());
    }
}
