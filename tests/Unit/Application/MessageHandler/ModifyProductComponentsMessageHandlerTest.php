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

namespace Sulu\Product\Tests\Unit\Application\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\ModifyProductComponentsMessage;
use Sulu\Product\Application\MessageHandler\ModifyProductComponentsMessageHandler;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductComponentsDataMapper;

#[CoversClass(ModifyProductComponentsMessageHandler::class)]
class ModifyProductComponentsMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ProductComponentsDataMapper> */
    private ObjectProphecy $componentsDataMapper;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ModifyProductComponentsMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->componentsDataMapper = $this->prophesize(ProductComponentsDataMapper::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new ModifyProductComponentsMessageHandler(
            $this->productRepository->reveal(),
            $this->componentsDataMapper->reveal(),
            $this->domainEventCollector->reveal(),
        );
    }

    public function testMapsTheComponentsOnTheUnlocalizedDraftOnly(): void
    {
        $product = new Product('parent');
        $localized = new ProductDimensionContent($product);
        $localized->setLocale('en');
        $live = new ProductDimensionContent($product);
        $live->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized = new ProductDimensionContent($product);
        $product->addDimensionContent($localized);
        $product->addDimensionContent($live);
        $product->addDimensionContent($unlocalized);

        $this->productRepository->getOneBy(['uuid' => 'parent'], Argument::type('array'))->willReturn($product);
        $this->componentsDataMapper->map($unlocalized, $unlocalized, ['components' => ['a', 'b']])->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::type(ProductModifiedEvent::class))->shouldBeCalledOnce();

        $result = ($this->handler)(new ModifyProductComponentsMessage(['uuid' => 'parent'], 'en', ['a', 'b']));

        $this->assertSame($product, $result);
    }

    public function testThrowsWithoutUnlocalizedDraft(): void
    {
        $product = new Product('parent');
        $this->productRepository->getOneBy(['uuid' => 'parent'], Argument::type('array'))->willReturn($product);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Product "parent" has no unlocalized draft content.');

        ($this->handler)(new ModifyProductComponentsMessage(['uuid' => 'parent'], 'en', []));
    }
}
