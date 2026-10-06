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
use Sulu\Product\Domain\Event\AttributeModifiedEvent;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

#[CoversClass(AttributeModifiedEvent::class)]
class AttributeModifiedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $attribute = $this->createAttribute();
        $event = new AttributeModifiedEvent($attribute, 'de', ['name' => 'Name DE']);

        $this->assertSame($attribute, $event->getAttribute());
        $this->assertSame('modified', $event->getEventType());
        $this->assertSame(['name' => 'Name DE'], $event->getEventPayload());
        $this->assertSame(AttributeInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('attribute-uuid', $event->getResourceId());
        $this->assertSame('de', $event->getResourceLocale());
        $this->assertSame('Name DE', $event->getResourceTitle());
        $this->assertSame('de', $event->getResourceTitleLocale());
        $this->assertSame(AttributeAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testResourceTitleIsNullWithoutTranslationInLocale(): void
    {
        $event = new AttributeModifiedEvent($this->createAttribute(), 'fr', []);

        $this->assertNull($event->getResourceTitle());
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
