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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Event\ProductTranslationAddedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\AdminProductIndexListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AdminProductIndexListener::class)]
class AdminProductIndexListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    private AdminProductIndexListener $listener;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->listener = new AdminProductIndexListener($this->messageBus->reveal());
    }

    public function testOnProductChangedWithProductCreatedEvent(): void
    {
        $product = new Product(new ProductFamily(), '123');
        $event = new ProductCreatedEvent($product, 'en', []);
        $expectedConfig = ReindexConfig::create()->withIndex('admin')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__123__en']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductModifiedEvent(): void
    {
        $product = new Product(new ProductFamily(), '456');
        $event = new ProductModifiedEvent($product, 'en', []);
        $expectedConfig = ReindexConfig::create()->withIndex('admin')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__456__en']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductRemovedEvent(): void
    {
        $product = new Product(new ProductFamily(), '789');
        $event = new ProductRemovedEvent($product->getUuid(), 'Uncool product', ['locales' => ['en', 'de']]);
        $expectedConfig = ReindexConfig::create()->withIndex('admin')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__789__en', ProductInterface::RESOURCE_KEY . '__789__de']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductRestoredEvent(): void
    {
        $product = new Product(new ProductFamily(), '222');
        $event = new ProductRestoredEvent($product, 'test product', ['locales' => ['de']], []);
        $expectedConfig = ReindexConfig::create()->withIndex('admin')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__222__de']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductTranslationAddedEvent(): void
    {
        $product = new Product(new ProductFamily(), '333');
        $event = new ProductTranslationAddedEvent($product, 'de', []);
        $expectedConfig = ReindexConfig::create()->withIndex('admin')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__333__de']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductTranslationRemovedEvent(): void
    {
        $product = new Product(new ProductFamily(), '444');
        $event = new ProductTranslationRemovedEvent($product, 'de');
        $expectedConfig = ReindexConfig::create()->withIndex('admin')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__444__de']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithRemovedEventWithoutLocalesDoesNotDispatch(): void
    {
        $product = new Product(new ProductFamily(), '789');
        $event = new ProductRemovedEvent($product->getUuid(), 'Uncool product', []);
        $this->messageBus->dispatch(\Prophecy\Argument::any())->shouldNotBeCalled();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithoutLocaleDoesNotDispatch(): void
    {
        $product = new Product(new ProductFamily(), '123');
        $event = new ProductCreatedEvent($product, '', []);
        $this->messageBus->dispatch(\Prophecy\Argument::any())->shouldNotBeCalled();
        $this->listener->onProductChanged($event);
    }
}
