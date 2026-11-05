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
use Sulu\Product\Application\Message\ApplyProductComponentTransitionMessage;
use Sulu\Product\Application\MessageHandler\ApplyProductComponentTransitionMessageHandler;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Exception\ProductComponentTransitionUnavailableException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

#[CoversClass(ApplyProductComponentTransitionMessageHandler::class)]
class ApplyProductComponentTransitionMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ApplyProductComponentTransitionMessageHandler $handler;

    private Product $product;

    private ProductDimensionContent $draft;

    private ProductDimensionContent $live;

    private Product $component;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
        $this->handler = new ApplyProductComponentTransitionMessageHandler(
            $this->productRepository->reveal(),
            $this->domainEventCollector->reveal(),
        );

        $this->product = new Product('parent');
        $this->component = new Product('component');
        $this->draft = new ProductDimensionContent($this->product);
        $this->live = new ProductDimensionContent($this->product);
        $this->live->setStage(DimensionContentInterface::STAGE_LIVE);
        $this->productRepository->getOneBy(['uuid' => 'parent'], Argument::type('array'))->willReturn($this->product);
    }

    private function publishedInEnglish(): void
    {
        $localizedLive = new ProductDimensionContent($this->product);
        $localizedLive->setStage(DimensionContentInterface::STAGE_LIVE);
        $localizedLive->setLocale('en');
        $this->product->addDimensionContent($this->draft);
        $this->product->addDimensionContent($this->live);
        $this->product->addDimensionContent($localizedLive);
    }

    private function message(string $transition): ApplyProductComponentTransitionMessage
    {
        return new ApplyProductComponentTransitionMessage(['uuid' => 'parent'], 'en', 'component', $transition);
    }

    public function testPublishCopiesTheRowWithItsPosition(): void
    {
        $this->publishedInEnglish();
        $this->draft->addComponent(new ProductComponent($this->draft, $this->component, 3));
        $this->domainEventCollector->collect(Argument::type(ProductModifiedEvent::class))->shouldBeCalledOnce();

        ($this->handler)($this->message('publish'));

        $this->assertCount(1, $this->live->getComponents());
        $this->assertSame($this->component, $this->live->getComponents()[0]->getComponent());
        $this->assertSame(3, $this->live->getComponents()[0]->getPosition());
        $this->assertCount(1, $this->draft->getComponents());
    }

    public function testUnpublishRemovesTheLiveRowOnly(): void
    {
        $this->publishedInEnglish();
        $this->draft->addComponent(new ProductComponent($this->draft, $this->component));
        $this->live->addComponent(new ProductComponent($this->live, $this->component));
        $this->live->addComponent(new ProductComponent($this->live, new Product('other')));
        $this->domainEventCollector->collect(Argument::type(ProductModifiedEvent::class))->shouldBeCalledOnce();

        ($this->handler)($this->message('unpublish'));

        $this->assertCount(1, $this->live->getComponents());
        $this->assertSame('other', $this->live->getComponents()[0]->getComponent()->getUuid());
        $this->assertCount(1, $this->draft->getComponents());
    }

    public function testPublishWithoutLocalizedLiveContentIsUnavailable(): void
    {
        $this->product->addDimensionContent($this->draft);
        $this->product->addDimensionContent($this->live);
        $this->draft->addComponent(new ProductComponent($this->draft, $this->component));

        $this->expectException(ProductComponentTransitionUnavailableException::class);
        ($this->handler)($this->message('publish'));
    }

    public function testPublishOfAnAlreadyLiveRowIsUnavailable(): void
    {
        $this->publishedInEnglish();
        $this->draft->addComponent(new ProductComponent($this->draft, $this->component));
        $this->live->addComponent(new ProductComponent($this->live, $this->component));

        $this->expectException(ProductComponentTransitionUnavailableException::class);
        ($this->handler)($this->message('publish'));
    }

    public function testPublishOfAMissingDraftRowIsUnavailable(): void
    {
        $this->publishedInEnglish();

        $this->expectException(ProductComponentTransitionUnavailableException::class);
        ($this->handler)($this->message('publish'));
    }

    public function testUnpublishOfADraftRowIsUnavailable(): void
    {
        $this->publishedInEnglish();
        $this->draft->addComponent(new ProductComponent($this->draft, $this->component));

        $this->expectException(ProductComponentTransitionUnavailableException::class);
        ($this->handler)($this->message('unpublish'));
    }
}
