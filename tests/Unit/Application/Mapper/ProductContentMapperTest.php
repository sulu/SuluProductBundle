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

namespace Sulu\Product\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Mapper\ProductContentMapper;
use Sulu\Product\Domain\Model\Product;

class ProductContentMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ContentPersisterInterface> */
    private ObjectProphecy $contentPersister;

    private ProductContentMapper $mapper;

    protected function setUp(): void
    {
        $this->contentPersister = $this->prophesize(ContentPersisterInterface::class);
        $this->mapper = new ProductContentMapper($this->contentPersister->reveal());
    }

    public function testMapProductDataPersistsDraftForLocale(): void
    {
        $product = new Product('prod-uuid');

        $this->contentPersister->persist(
            $product,
            Argument::that(fn (array $data): bool => 'PROD-001' === ($data['code'] ?? null)),
            [
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ],
        )->shouldBeCalledOnce();

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
            'code' => 'PROD-001',
            'template' => 'product',
        ]);
    }

    public function testMapProductDataRequiresStringLocale(): void
    {
        $product = new Product('prod-uuid');

        $this->contentPersister->persist(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(\InvalidArgumentException::class);

        $this->mapper->mapProductData($product, ['template' => 'product']);
    }
}
