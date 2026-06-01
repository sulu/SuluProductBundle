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

namespace Sulu\Product\Tests\Unit\Domain\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductRemovedEvent::class)]
class ProductRemovedEventTest extends TestCase
{
    public function testGetEventType(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title');

        $this->assertSame('removed', $event->getEventType());
    }

    public function testGetEventContextDefaultsToEmptyArray(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title');

        $this->assertSame([], $event->getEventContext());
    }

    public function testGetEventContextWithProvidedContext(): void
    {
        $context = ['locales' => ['en', 'de']];

        $event = new ProductRemovedEvent('product-id', 'Title', $context);

        $this->assertSame($context, $event->getEventContext());
    }

    public function testGetResourceKey(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title');

        $this->assertSame(ProductInterface::RESOURCE_KEY, $event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title');

        $this->assertSame('product-id', $event->getResourceId());
    }

    public function testGetResourceTitle(): void
    {
        $event = new ProductRemovedEvent('product-id', 'My Title');

        $this->assertSame('My Title', $event->getResourceTitle());
    }

    public function testGetResourceTitleWithNull(): void
    {
        $event = new ProductRemovedEvent('product-id', null);

        $this->assertNull($event->getResourceTitle());
    }

    public function testGetResourceSecurityContext(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title');

        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testGetAllLocalesReturnsNullWhenAbsent(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title');

        $this->assertNull($event->getAllLocales());
    }

    public function testGetAllLocalesReturnsLocalesFromContext(): void
    {
        $event = new ProductRemovedEvent('product-id', 'Title', ['locales' => ['en', 'fr']]);

        $this->assertSame(['en', 'fr'], $event->getAllLocales());
    }
}
