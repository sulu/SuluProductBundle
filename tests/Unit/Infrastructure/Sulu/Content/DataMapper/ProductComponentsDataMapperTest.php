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
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductComponentsDataMapper;

#[CoversClass(ProductComponentsDataMapper::class)]
final class ProductComponentsDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private ProductComponentsDataMapper $mapper;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);

        $this->mapper = new ProductComponentsDataMapper(
            $this->productRepository->reveal(),
        );
    }

    public function testEarlyReturnWhenUnlocalizedNotProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($other->reveal(), $other->reveal(), ['components' => ['uuid-b']]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->addToAssertionCount(1);
    }

    public function testLeavesTheLiveRowsAlone(): void
    {
        $source = new Product('uuid-source');
        $live = new ProductDimensionContent($source);
        $live->setStage(DimensionContentInterface::STAGE_LIVE);
        $live->addComponent(new ProductComponent($live, new Product('uuid-b')));

        $this->mapper->map($live, $live, ['components' => []]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertCount(1, $live->getComponents());
    }

    public function testIgnoresWhenNoComponentsKey(): void
    {
        $source = new Product('uuid-source');
        $unloc = new ProductDimensionContent($source);
        $unloc->addComponent(new ProductComponent($unloc, new Product('uuid-b')));

        $this->mapper->map($unloc, new ProductDimensionContent($source), ['locale' => 'en']);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertCount(1, $unloc->getComponents());
    }

    public function testThrowsOnNonListValue(): void
    {
        $source = new Product('uuid-source');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected a list of product uuids for the components, got "string".');

        $this->mapper->map(new ProductDimensionContent($source), new ProductDimensionContent($source), ['components' => 'uuid-b']);
    }

    public function testNullClearsComponents(): void
    {
        $source = new Product('uuid-source');
        $unloc = new ProductDimensionContent($source);
        $unloc->addComponent(new ProductComponent($unloc, new Product('uuid-b')));

        $this->mapper->map($unloc, new ProductDimensionContent($source), ['components' => null]);

        $this->productRepository->findBy(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertSame([], $unloc->getComponents());
    }

    public function testMapsSubmittedComponentsInOrderOnUnlocalizedOnly(): void
    {
        $source = new Product('uuid-source');
        $loc = new ProductDimensionContent($source);
        $unloc = new ProductDimensionContent($source);

        $this->productRepository->findBy(['uuids' => ['uuid-c', 'uuid-b']])
            ->willReturn([new Product('uuid-b'), new Product('uuid-c')]);

        $this->mapper->map($unloc, $loc, ['components' => ['uuid-c', 'uuid-b']]);

        $result = $unloc->getComponents();
        self::assertCount(2, $result);
        self::assertSame('uuid-c', $result[0]->getComponent()->getUuid());
        self::assertSame(0, $result[0]->getPosition());
        self::assertSame('uuid-b', $result[1]->getComponent()->getUuid());
        self::assertSame(1, $result[1]->getPosition());
        self::assertSame([], $loc->getComponents());
    }

    public function testDropsNonStringUnknownAndSelfEntries(): void
    {
        $source = new Product('uuid-source');
        $unloc = new ProductDimensionContent($source);

        $this->productRepository->findBy(['uuids' => ['uuid-source', 'uuid-unknown', 'uuid-b']])
            ->willReturn([$source, new Product('uuid-b')]);

        $this->mapper->map($unloc, new ProductDimensionContent($source), [
            'components' => ['uuid-source', 42, 'uuid-unknown', 'uuid-b'],
        ]);

        $result = $unloc->getComponents();
        self::assertCount(1, $result);
        self::assertSame('uuid-b', $result[0]->getComponent()->getUuid());
        self::assertSame(0, $result[0]->getPosition());
    }

    public function testKeepsExistingRowsAndRemovesTheOthers(): void
    {
        $source = new Product('uuid-source');
        $unloc = new ProductDimensionContent($source);
        $kept = new ProductComponent($unloc, new Product('uuid-b'), 0);
        $removed = new ProductComponent($unloc, new Product('uuid-c'), 1);
        $unloc->addComponent($kept);
        $unloc->addComponent($removed);

        $this->productRepository->findBy(['uuids' => ['uuid-d', 'uuid-b']])
            ->willReturn([new Product('uuid-b'), new Product('uuid-d')]);

        $this->mapper->map($unloc, new ProductDimensionContent($source), ['components' => ['uuid-d', 'uuid-b']]);

        $result = $unloc->getComponents();
        self::assertCount(2, $result);
        self::assertSame('uuid-d', $result[0]->getComponent()->getUuid());
        self::assertSame($kept, $result[1]);
        self::assertSame(1, $kept->getPosition());
        self::assertNotContains($removed, $result);
    }

    public function testAllowsTheSameProductSeveralTimes(): void
    {
        $source = new Product('uuid-source');
        $unloc = new ProductDimensionContent($source);
        $first = new ProductComponent($unloc, new Product('uuid-b'), 0);
        $unloc->addComponent($first);

        $this->productRepository->findBy(['uuids' => ['uuid-b']])
            ->willReturn([new Product('uuid-b')]);

        $this->mapper->map($unloc, new ProductDimensionContent($source), ['components' => ['uuid-b', 'uuid-b']]);

        $result = $unloc->getComponents();
        self::assertCount(2, $result);
        self::assertSame($first, $result[0]);
        self::assertNotSame($first, $result[1]);
        self::assertSame(1, $result[1]->getPosition());
    }
}
