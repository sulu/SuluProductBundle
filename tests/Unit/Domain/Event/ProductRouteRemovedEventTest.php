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
use Sulu\Product\Domain\Event\ProductRouteRemovedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductRouteRemovedEvent::class)]
class ProductRouteRemovedEventTest extends TestCase
{
    private string $productId = 'product-id-1';

    private string $productTitle = 'Product Title';

    private string $productTitleLocale = 'en';

    private string $route = '/some/route';

    private ProductRouteRemovedEvent $event;

    protected function setUp(): void
    {
        $this->event = new ProductRouteRemovedEvent(
            $this->productId,
            $this->productTitle,
            $this->productTitleLocale,
            $this->route
        );
    }

    public function testGetEventType(): void
    {
        $this->assertSame('route_removed', $this->event->getEventType());
    }

    public function testGetEventContext(): void
    {
        $this->assertSame(['route' => $this->route], $this->event->getEventContext());
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $this->assertSame($this->productId, $this->event->getResourceId());
    }

    public function testGetResourceTitle(): void
    {
        $this->assertSame($this->productTitle, $this->event->getResourceTitle());
    }

    public function testGetResourceTitleLocale(): void
    {
        $this->assertSame($this->productTitleLocale, $this->event->getResourceTitleLocale());
    }

    public function testGetResourceSecurityContext(): void
    {
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $this->event->getResourceSecurityContext());
    }
}
