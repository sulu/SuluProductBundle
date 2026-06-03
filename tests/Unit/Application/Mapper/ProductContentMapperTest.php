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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Product\Application\Mapper\ProductContentMapper;
use Sulu\Product\Domain\Model\Product;

#[CoversClass(ProductContentMapper::class)]
class ProductContentMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ContentPersisterInterface> */
    private ObjectProphecy $contentPersister;

    private ProductContentMapper $mapper;

    protected function setUp(): void
    {
        $this->contentPersister = $this->prophesize(ContentPersisterInterface::class);

        $this->mapper = new ProductContentMapper(
            $this->contentPersister->reveal(),
        );
    }

    public function testMapProductDataPersistsContentWhenTemplateGiven(): void
    {
        $product = new Product();
        $data = [
            'template' => 'default',
            'locale' => 'en',
            'title' => 'Foo',
        ];

        $this->contentPersister->persist(
            $product,
            $data,
            ['locale' => 'en'],
        )->shouldBeCalledOnce();

        $this->mapper->mapProductData($product, $data);
    }

    public function testMapProductDataDoesNothingWhenTemplateMissing(): void
    {
        $product = new Product();

        $this->contentPersister->persist(Argument::cetera())->shouldNotBeCalled();

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
            'title' => 'Foo',
        ]);
    }

    public function testMapProductDataThrowsWhenLocaleIsNotAString(): void
    {
        $product = new Product();

        $this->contentPersister->persist(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(\InvalidArgumentException::class);

        $this->mapper->mapProductData($product, [
            'template' => 'default',
            'locale' => 123,
        ]);
    }

    public function testMapProductDataThrowsWhenLocaleIsMissing(): void
    {
        $product = new Product();

        $this->contentPersister->persist(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(\InvalidArgumentException::class);

        $this->mapper->mapProductData($product, [
            'template' => 'default',
        ]);
    }
}
