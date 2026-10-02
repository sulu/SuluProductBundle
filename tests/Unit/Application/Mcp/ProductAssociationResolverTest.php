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

namespace Sulu\Product\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Mcp\ProductAssociationResolver;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Exception\InvalidProductAssociationException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

#[CoversClass(ProductAssociationResolver::class)]
final class ProductAssociationResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $repository;
    private ProductAssociationResolver $resolver;

    protected function setUp(): void
    {
        $this->repository = $this->prophesize(ProductRepositoryInterface::class);
        $this->repository->findOneBy(['uuid' => 'a-uuid'])->willReturn(new Product('a-uuid'));
        $this->repository->findOneBy(['uuid' => 'B-CODE'])->willReturn(null);
        $this->repository->findOneBy(['uuid' => 'nope'])->willReturn(null);
        $this->repository->findOneBy(['code' => 'B-CODE', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])->willReturn(new Product('b-uuid'));
        $this->repository->findOneBy(['code' => 'nope', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])->willReturn(null);

        $this->resolver = new ProductAssociationResolver(
            $this->repository->reveal(),
            new ProductAssociationTypeRegistry(['accessory' => ['label' => 'Accessory']]),
        );
    }

    public function testItResolvesUuidsAndCodesAndRemovesDuplicates(): void
    {
        $result = $this->resolver->resolve(['accessory' => ['a-uuid', 'B-CODE', 'a-uuid']], 'en');

        $this->assertSame(['accessory' => ['a-uuid', 'b-uuid']], $result);
    }

    public function testNullAndEmptyListsBothMeanNoProducts(): void
    {
        $result = $this->resolver->resolve(['accessory' => null], 'en');

        $this->assertSame(['accessory' => []], $result);
    }

    public function testItRejectsAnUnknownTargetNamingIt(): void
    {
        $this->expectException(InvalidProductAssociationException::class);
        $this->expectExceptionMessage('nope');

        $this->resolver->resolve(['accessory' => ['nope']], 'en');
    }

    public function testItRejectsAValueThatIsNotAList(): void
    {
        $this->expectException(InvalidProductAssociationException::class);

        $this->resolver->resolve(['accessory' => 'a-uuid'], 'en');
    }

    public function testItRejectsAnEntryThatIsNotAString(): void
    {
        $this->expectException(InvalidProductAssociationException::class);

        $this->resolver->resolve(['accessory' => [12]], 'en');
    }
}
