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
use Sulu\Product\Domain\Event\AttributeRestoredEvent;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

#[CoversClass(AttributeRestoredEvent::class)]
class AttributeRestoredEventTest extends TestCase
{
    public function testGetters(): void
    {
        $attribute = $this->createAttribute();
        $event = new AttributeRestoredEvent($attribute, ['defaultLocale' => 'en']);

        $this->assertSame($attribute, $event->getAttribute());
        $this->assertSame('restored', $event->getEventType());
        $this->assertSame(['defaultLocale' => 'en'], $event->getEventPayload());
        $this->assertSame(AttributeInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('attribute-uuid', $event->getResourceId());
        $this->assertSame('Name EN', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame(AttributeAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testResourceTitleIsNullWithoutDefaultLocale(): void
    {
        $event = new AttributeRestoredEvent(new Attribute(new AttributeGroup()), []);

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceTitleLocale());
    }

    private function createAttribute(): AttributeInterface
    {
        $attribute = new Attribute(new AttributeGroup(), 'attribute-uuid');
        $attribute->setDefaultLocale('en');
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Name EN'));
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Name DE'));

        return $attribute;
    }
}
