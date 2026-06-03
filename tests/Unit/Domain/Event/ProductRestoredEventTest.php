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
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductRestoredEvent::class)]
class ProductRestoredEventTest extends TestCase
{
    private Product $product;

    private string $productTitle = 'Restored Product';

    /** @var array{locales?: string[]} */
    private array $context = ['locales' => ['en', 'de']];

    /** @var array<string, mixed> */
    private array $payload = ['foo' => 'bar'];

    private ProductRestoredEvent $event;

    protected function setUp(): void
    {
        $this->product = new Product('uuid-restored');
        $this->event = new ProductRestoredEvent($this->product, $this->productTitle, $this->context, $this->payload);
    }

    public function testGetProduct(): void
    {
        $this->assertSame($this->product, $this->event->getProduct());
    }

    public function testGetEventType(): void
    {
        $this->assertSame('restored', $this->event->getEventType());
    }

    public function testGetEventPayload(): void
    {
        $this->assertSame($this->payload, $this->event->getEventPayload());
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $this->assertSame('uuid-restored', $this->event->getResourceId());
    }

    public function testGetResourceTitle(): void
    {
        $this->assertSame($this->productTitle, $this->event->getResourceTitle());
    }

    public function testGetResourceTitleWithNull(): void
    {
        $event = new ProductRestoredEvent($this->product, null, [], []);

        $this->assertNull($event->getResourceTitle());
    }

    public function testGetResourceSecurityContext(): void
    {
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $this->event->getResourceSecurityContext());
    }

    public function testGetAllLocales(): void
    {
        $this->assertSame(['en', 'de'], $this->event->getAllLocales());
    }

    public function testGetAllLocalesReturnsNullWhenAbsent(): void
    {
        $event = new ProductRestoredEvent($this->product, $this->productTitle, [], []);

        $this->assertNull($event->getAllLocales());
    }
}
