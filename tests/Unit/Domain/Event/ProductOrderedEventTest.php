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
use Sulu\Product\Domain\Event\ProductOrderedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductOrderedEvent::class)]
class ProductOrderedEventTest extends TestCase
{
    private Product $product;

    private string $locale = 'en';

    private int $targetPosition = 3;

    private ProductOrderedEvent $event;

    protected function setUp(): void
    {
        $this->product = new Product(new ProductFamily(), 'uuid-ordered');
        $this->event = new ProductOrderedEvent($this->product, $this->locale, $this->targetPosition);
    }

    public function testGetProduct(): void
    {
        $this->assertSame($this->product, $this->event->getProduct());
    }

    public function testGetEventType(): void
    {
        $this->assertSame('ordered', $this->event->getEventType());
    }

    public function testGetEventContext(): void
    {
        $this->assertSame(['targetPosition' => $this->targetPosition], $this->event->getEventContext());
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $this->assertSame('uuid-ordered', $this->event->getResourceId());
    }

    public function testGetResourceTitle(): void
    {
        $this->assertNull($this->event->getResourceTitle());
    }

    public function testGetResourceTitleLocale(): void
    {
        $this->assertSame($this->locale, $this->event->getResourceTitleLocale());
    }

    public function testGetResourceSecurityContext(): void
    {
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $this->event->getResourceSecurityContext());
    }
}
