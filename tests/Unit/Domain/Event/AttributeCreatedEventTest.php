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
use Sulu\Product\Domain\Event\AttributeCreatedEvent;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

#[CoversClass(AttributeCreatedEvent::class)]
class AttributeCreatedEventTest extends TestCase
{
    private Attribute $attribute;

    /** @var array<string, mixed> */
    private array $payload = ['key' => 'weight', 'type' => AttributeInterface::TYPE_NUMBER];

    private AttributeCreatedEvent $event;

    protected function setUp(): void
    {
        $this->attribute = new Attribute(new AttributeGroup());
        $this->attribute->setUuid('uuid-1');
        $this->attribute->addTranslation(new AttributeTranslation($this->attribute, 'en', 'Weight'));
        $this->event = new AttributeCreatedEvent($this->attribute, 'en', $this->payload);
    }

    public function testGetAttribute(): void
    {
        $this->assertSame($this->attribute, $this->event->getAttribute());
    }

    public function testGetEventType(): void
    {
        $this->assertSame('created', $this->event->getEventType());
    }

    public function testGetEventPayload(): void
    {
        $this->assertSame($this->payload, $this->event->getEventPayload());
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(AttributeInterface::RESOURCE_KEY, $this->event->getResourceKey());
    }

    public function testGetResourceId(): void
    {
        $this->assertSame('uuid-1', $this->event->getResourceId());
    }

    public function testGetResourceLocale(): void
    {
        $this->assertSame('en', $this->event->getResourceLocale());
    }

    public function testGetResourceTitle(): void
    {
        $this->assertSame('Weight', $this->event->getResourceTitle());
    }

    public function testGetResourceTitleIsNullWithoutTranslation(): void
    {
        $this->assertNull((new AttributeCreatedEvent($this->attribute, 'de', []))->getResourceTitle());
    }

    public function testGetResourceTitleLocale(): void
    {
        $this->assertSame('en', $this->event->getResourceTitleLocale());
    }

    public function testGetResourceSecurityContext(): void
    {
        $this->assertSame(AttributeAdmin::SECURITY_CONTEXT, $this->event->getResourceSecurityContext());
    }
}
