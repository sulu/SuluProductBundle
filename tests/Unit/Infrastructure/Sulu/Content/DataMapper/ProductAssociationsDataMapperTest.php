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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductAssociationsDataMapper;

#[CoversClass(ProductAssociationsDataMapper::class)]
final class ProductAssociationsDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private ProductAssociationsDataMapper $mapper;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);

        $this->mapper = new ProductAssociationsDataMapper(
            $this->productRepository->reveal(),
        );
    }

    public function testEarlyReturnWhenUnlocalizedNotProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($other->reveal(), $other->reveal(), ['associations' => ['alternative' => ['uuid-b']]]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenLocalizedNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $locOther = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($unloc->reveal(), $locOther->reveal(), ['associations' => ['alternative' => ['uuid-b']]]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->addToAssertionCount(1);
    }

    public function testIgnoresWhenNoAssociationsKey(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $this->mapper->map($unloc, $loc, ['locale' => 'en']);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertSame([], $loc->getAssociations());
    }

    public function testMapsSubmittedAssociations(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');
        $productC = new Product('uuid-c');

        $this->productRepository->findBy(['uuids' => ['uuid-b', 'uuid-c']])
            ->willReturn([$productB, $productC]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-b', 'uuid-c']],
        ]);

        $result = $loc->getAssociationsByType('alternative');
        self::assertCount(2, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
        self::assertSame(0, $result[0]->getPosition());
        self::assertSame('uuid-c', $result[1]->getTarget()->getUuid());
        self::assertSame(1, $result[1]->getPosition());
    }

    public function testMapsDigitOnlyTypeAsString(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $this->productRepository->findBy(['uuids' => ['uuid-b']])
            ->willReturn([new Product('uuid-b')]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['123' => ['uuid-b']],
        ]);

        $result = $loc->getAssociationsByType('123');
        self::assertCount(1, $result);
        self::assertSame('123', $result[0]->getType());
    }

    public function testRemovesDroppedTargets(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');
        $productC = new Product('uuid-c');
        $loc->addAssociation(new ProductAssociation($loc, $productB, 'alternative', 0));
        $loc->addAssociation(new ProductAssociation($loc, $productC, 'alternative', 1));

        $this->productRepository->findBy(['uuids' => ['uuid-b']])
            ->willReturn([$productB]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-b']],
        ]);

        $result = $loc->getAssociationsByType('alternative');
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
    }

    public function testReordersByIndex(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');
        $productC = new Product('uuid-c');
        $associationB = new ProductAssociation($loc, $productB, 'alternative', 0);
        $associationC = new ProductAssociation($loc, $productC, 'alternative', 1);
        $loc->addAssociation($associationB);
        $loc->addAssociation($associationC);

        $this->productRepository->findBy(['uuids' => ['uuid-c', 'uuid-b']])
            ->willReturn([$productC, $productB]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-c', 'uuid-b']],
        ]);

        self::assertSame(0, $associationC->getPosition());
        self::assertSame(1, $associationB->getPosition());

        $result = $loc->getAssociationsByType('alternative');
        self::assertSame($associationC, $result[0]);
        self::assertSame($associationB, $result[1]);
    }

    public function testMapsTypeThatIsNoLongerRegistered(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');

        $this->productRepository->findBy(['uuids' => ['uuid-b']])
            ->willReturn([$productB]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['removed-type' => ['uuid-b']],
        ]);

        $result = $loc->getAssociationsByType('removed-type');
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
    }

    public function testTreatsNullSelectionAsEmptySelection(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');
        $loc->addAssociation(new ProductAssociation($loc, $productB, 'alternative', 0));

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => null],
        ]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertSame([], $loc->getAssociations());
    }

    public function testIgnoresNonArrayAssociations(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');
        $association = new ProductAssociation($loc, $productB, 'alternative', 0);
        $loc->addAssociation($association);

        $this->mapper->map($unloc, $loc, ['associations' => null]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertSame([$association], $loc->getAssociations());
    }

    public function testThrowsOnScalarSelection(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a list of product uuids for association type "alternative", got "string".');

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => 'uuid-b'],
        ]);
    }

    public function testSkipsNonStringTargets(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');

        $this->productRepository->findBy(['uuids' => ['uuid-b']])
            ->willReturn([$productB]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-b', 42, null]],
        ]);

        $result = $loc->getAssociationsByType('alternative');
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
    }

    public function testSkipsMissingTarget(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');

        $this->productRepository->findBy(['uuids' => ['uuid-b', 'uuid-missing']])
            ->willReturn([$productB]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-b', 'uuid-missing']],
        ]);

        $result = $loc->getAssociationsByType('alternative');
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
    }

    public function testRejectsSelfReference(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $this->productRepository->findBy(['uuids' => ['uuid-source']])
            ->willReturn([]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-source']],
        ]);

        self::assertSame([], $loc->getAssociations());
    }

    public function testDedupesDuplicateSubmittedTargets(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $productB = new Product('uuid-b');

        $this->productRepository->findBy(['uuids' => ['uuid-b']])
            ->willReturn([$productB]);

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-b', 'uuid-b']],
        ]);

        $result = $loc->getAssociationsByType('alternative');
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
        self::assertSame(0, $result[0]->getPosition());

        $this->mapper->map($unloc, $loc, [
            'associations' => ['alternative' => ['uuid-b']],
        ]);

        $result = $loc->getAssociationsByType('alternative');
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getTarget()->getUuid());
        self::assertSame(0, $result[0]->getPosition());
    }
}
