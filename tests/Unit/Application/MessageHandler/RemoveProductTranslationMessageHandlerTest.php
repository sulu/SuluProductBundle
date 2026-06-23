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

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\RemoveProductTranslationMessage;
use Sulu\Product\Application\MessageHandler\RemoveProductTranslationMessageHandler;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class RemoveProductTranslationMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private RemoveProductTranslationMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new RemoveProductTranslationMessageHandler(
            $this->productRepository->reveal(),
            $this->domainEventCollector->reveal(),
            null,
        );
    }

    public function testRemoveDirectLocaleMatch(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');

        $dc = new ProductDimensionContent($product);
        $dc->setLocale('en');
        $dc->setStage(DimensionContentInterface::STAGE_DRAFT);
        $product->addDimensionContent($dc);

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->removeDimensionContent($dc)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductTranslationRemovedEvent::class))
            ->shouldBeCalledOnce();

        $message = new RemoveProductTranslationMessage(['uuid' => 'prod-uuid'], 'en');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
        $this->assertCount(0, $product->getDimensionContents());
    }

    public function testRemoveGhostLocaleMatchNoRemainingLocales(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');

        $dc = new ProductDimensionContent($product);
        $dc->setLocale(null);
        $dc->setGhostLocale('en');
        $dc->addAvailableLocale('en');
        $product->addDimensionContent($dc);

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->removeDimensionContent($dc)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductTranslationRemovedEvent::class))
            ->shouldBeCalledOnce();

        $message = new RemoveProductTranslationMessage(['uuid' => 'prod-uuid'], 'en');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
        $this->assertCount(0, $product->getDimensionContents());
    }

    public function testUpdateGhostLocaleWhenRemainingLocales(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');

        $dc = new ProductDimensionContent($product);
        $dc->setLocale(null);
        $dc->setGhostLocale('en');
        $dc->addAvailableLocale('en');
        $dc->addAvailableLocale('de');
        $dc->addAvailableLocale('fr');
        $product->addDimensionContent($dc);

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->removeDimensionContent(Argument::any())
            ->shouldNotBeCalled();

        $this->domainEventCollector->collect(Argument::type(ProductTranslationRemovedEvent::class))
            ->shouldBeCalledOnce();

        $message = new RemoveProductTranslationMessage(['uuid' => 'prod-uuid'], 'en');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
        $this->assertSame('de', $dc->getGhostLocale());
        $this->assertSame(['de', 'fr'], $dc->getAvailableLocales());
    }

    public function testRemoveUpdatesAvailableLocalesOnUnlocalizedContent(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');

        $dc = new ProductDimensionContent($product);
        $dc->setLocale(null);
        $dc->addAvailableLocale('en');
        $dc->addAvailableLocale('de');
        $product->addDimensionContent($dc);

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->removeDimensionContent(Argument::any())
            ->shouldNotBeCalled();

        $this->domainEventCollector->collect(Argument::type(ProductTranslationRemovedEvent::class))
            ->shouldBeCalledOnce();

        $message = new RemoveProductTranslationMessage(['uuid' => 'prod-uuid'], 'en');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
        $this->assertSame(['de'], $dc->getAvailableLocales());
    }
}
