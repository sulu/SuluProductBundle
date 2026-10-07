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
use Sulu\Product\Domain\Event\ProductWorkflowTransitionRequestEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductWorkflowTransitionRequestEvent::class)]
class ProductWorkflowTransitionRequestEventTest extends TestCase
{
    private Product $product;

    private ProductWorkflowTransitionRequestEvent $event;

    protected function setUp(): void
    {
        $this->product = new Product('uuid-request');
        $this->event = new ProductWorkflowTransitionRequestEvent($this->product, 'rejected', 'en', ['comment' => 'Too short']);
    }

    public function testGetProduct(): void
    {
        $this->assertSame($this->product, $this->event->getProduct());
    }

    public function testGetAction(): void
    {
        $this->assertSame('rejected', $this->event->getAction());
    }

    public function testGetEventType(): void
    {
        $this->assertSame('workflow_transition_request.rejected', $this->event->getEventType());
    }

    public function testGetEventContext(): void
    {
        $this->assertSame(['comment' => 'Too short'], $this->event->getEventContext());
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $this->assertSame('uuid-request', $this->event->getResourceId());
    }

    public function testGetResourceLocale(): void
    {
        $this->assertSame('en', $this->event->getResourceLocale());
    }

    public function testGetResourceTitleWithoutDimensionContent(): void
    {
        $this->assertNull($this->event->getResourceTitle());
    }

    public function testGetResourceTitleFromDimensionContentOfTheLocale(): void
    {
        $dimensionContent = new ProductDimensionContent($this->product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setTitle('Monstera');
        $this->product->addDimensionContent($dimensionContent);

        $this->assertSame('Monstera', $this->event->getResourceTitle());
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
