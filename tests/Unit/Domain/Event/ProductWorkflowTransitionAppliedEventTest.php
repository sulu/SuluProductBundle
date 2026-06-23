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
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductWorkflowTransitionAppliedEvent::class)]
class ProductWorkflowTransitionAppliedEventTest extends TestCase
{
    private Product $product;

    private ProductWorkflowTransitionAppliedEvent $event;

    protected function setUp(): void
    {
        $this->product = new Product(new ProductFamily(), 'uuid-workflow');
        $this->event = new ProductWorkflowTransitionAppliedEvent($this->product, 'publish', 'en');
    }

    public function testGetProduct(): void
    {
        $this->assertSame($this->product, $this->event->getProduct());
    }

    public function testGetWorkflowTransitionName(): void
    {
        $this->assertSame('publish', $this->event->getWorkflowTransitionName());
    }

    public function testGetEventType(): void
    {
        $this->assertSame('workflow_transition.publish', $this->event->getEventType());
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $this->assertSame('uuid-workflow', $this->event->getResourceId());
    }

    public function testGetResourceLocale(): void
    {
        $this->assertSame('en', $this->event->getResourceLocale());
    }

    public function testGetResourceTitle(): void
    {
        $this->assertNull($this->event->getResourceTitle());
    }

    public function testGetResourceTitleLocale(): void
    {
        $this->assertSame('en', $this->event->getResourceTitleLocale());
    }

    public function testGetResourceSecurityContext(): void
    {
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $this->event->getResourceSecurityContext());
    }
}
