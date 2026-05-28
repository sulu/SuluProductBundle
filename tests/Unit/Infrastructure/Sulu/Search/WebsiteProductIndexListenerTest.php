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
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\WebsiteProductIndexListener;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(WebsiteProductIndexListener::class)]
class WebsiteProductIndexListenerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    private WebsiteProductIndexListener $listener;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->listener = new WebsiteProductIndexListener($this->messageBus->reveal());
    }

    public function testOnProductChangedWithProductWorkflowTransitionAppliedEvent(): void
    {
        $product = new Product('123');
        $event = new ProductWorkflowTransitionAppliedEvent($product, DimensionContentInterface::STAGE_LIVE, 'en');
        $expectedConfig = ReindexConfig::create()->withIndex('website')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__123__en']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductRemovedEvent(): void
    {
        $product = new Product('789');
        $event = new ProductRemovedEvent($product->getUuid(), 'Uncool product', ['locales' => ['en', 'de']]);
        $expectedConfig = ReindexConfig::create()->withIndex('website')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__789__en', ProductInterface::RESOURCE_KEY . '__789__de']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }

    public function testOnProductChangedWithProductTranslationRemovedEvent(): void
    {
        $product = new Product('444');
        $event = new ProductTranslationRemovedEvent($product, 'de');
        $expectedConfig = ReindexConfig::create()->withIndex('website')->withIdentifiers([ProductInterface::RESOURCE_KEY . '__444__de']);
        $this->messageBus->dispatch($expectedConfig)->willReturn(new Envelope($expectedConfig))->shouldBeCalledOnce();
        $this->listener->onProductChanged($event);
    }
}
