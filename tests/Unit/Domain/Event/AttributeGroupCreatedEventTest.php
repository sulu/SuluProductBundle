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
use Sulu\Product\Domain\Event\AttributeGroupCreatedEvent;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;

#[CoversClass(AttributeGroupCreatedEvent::class)]
class AttributeGroupCreatedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $attributeGroup = $this->createAttributeGroup();
        $event = new AttributeGroupCreatedEvent($attributeGroup, 'de', ['name' => 'Name DE']);

        $this->assertSame($attributeGroup, $event->getAttributeGroup());
        $this->assertSame('created', $event->getEventType());
        $this->assertSame(['name' => 'Name DE'], $event->getEventPayload());
        $this->assertSame(AttributeGroupInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('attributeGroup-uuid', $event->getResourceId());
        $this->assertSame('de', $event->getResourceLocale());
        $this->assertSame('Name DE', $event->getResourceTitle());
        $this->assertSame('de', $event->getResourceTitleLocale());
        $this->assertSame(AttributeGroupAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testResourceTitleIsNullWithoutTranslationInLocale(): void
    {
        $event = new AttributeGroupCreatedEvent($this->createAttributeGroup(), 'fr', []);

        $this->assertNull($event->getResourceTitle());
    }

    private function createAttributeGroup(): AttributeGroupInterface
    {
        $attributeGroup = new AttributeGroup('attributeGroup-uuid');
        $attributeGroup->setDefaultLocale('en');
        $attributeGroup->addTranslation(new AttributeGroupTranslation($attributeGroup, 'en', 'Name EN'));
        $attributeGroup->addTranslation(new AttributeGroupTranslation($attributeGroup, 'de', 'Name DE'));

        return $attributeGroup;
    }
}
