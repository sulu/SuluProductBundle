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
use Sulu\Product\Domain\Model\ProductInterface;
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
        $this->repository->findOneBy(['code' => 'B-CODE', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT, 'loadGhost' => true])->willReturn(new Product('b-uuid'));
        $this->repository->findOneBy(['code' => 'nope', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT, 'loadGhost' => true])->willReturn(null);

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

    public function testItRejectsAnUnknownTypeAndPointsToTheTypeList(): void
    {
        try {
            $this->resolver->resolve(['bogus' => ['a-uuid']], 'en');
            $this->fail('Expected an InvalidProductAssociationException.');
        } catch (InvalidProductAssociationException $e) {
            $this->assertStringContainsString('"bogus"', $e->getMessage());
            $this->assertStringContainsString('sulu_product_association_type_list', $e->getHint());
        }
    }

    public function testItRejectsAProductAssociatedWithItself(): void
    {
        $this->expectException(InvalidProductAssociationException::class);
        $this->expectExceptionMessage('itself');

        $this->resolver->resolve(['accessory' => ['a-uuid']], 'en', 'a-uuid');
    }

    public function testItRejectsAVariantAsTarget(): void
    {
        $variant = new Product('v-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $this->repository->findOneBy(['uuid' => 'v-uuid'])->willReturn($variant);

        $this->expectException(InvalidProductAssociationException::class);
        $this->expectExceptionMessage('variant');

        $this->resolver->resolve(['accessory' => ['v-uuid']], 'en');
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
