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
use Sulu\Product\Domain\Event\ProductTranslationCopiedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductTranslationCopiedEvent::class)]
class ProductTranslationCopiedEventTest extends TestCase
{
    private Product $product;

    private string $locale = 'de';

    private string $sourceLocale = 'en';

    /** @var array<string, mixed> */
    private array $payload = ['copied' => true];

    private ProductTranslationCopiedEvent $event;

    protected function setUp(): void
    {
        $this->product = new Product(new ProductFamily(), 'uuid-translation-copied');
        $this->event = new ProductTranslationCopiedEvent(
            $this->product,
            $this->locale,
            $this->sourceLocale,
            $this->payload
        );
    }

    public function testGetProduct(): void
    {
        $this->assertSame($this->product, $this->event->getProduct());
    }

    public function testGetEventType(): void
    {
        $this->assertSame('translation_copied', $this->event->getEventType());
    }

    public function testGetEventContext(): void
    {
        $this->assertSame(['sourceLocale' => $this->sourceLocale], $this->event->getEventContext());
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
        $this->assertSame('uuid-translation-copied', $this->event->getResourceId());
    }

    public function testGetResourceLocale(): void
    {
        $this->assertSame($this->locale, $this->event->getResourceLocale());
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
