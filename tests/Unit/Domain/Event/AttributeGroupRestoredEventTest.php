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
use Sulu\Product\Domain\Event\AttributeGroupRestoredEvent;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;

#[CoversClass(AttributeGroupRestoredEvent::class)]
class AttributeGroupRestoredEventTest extends TestCase
{
    public function testGetters(): void
    {
        $attributeGroup = $this->createAttributeGroup();
        $event = new AttributeGroupRestoredEvent($attributeGroup, ['defaultLocale' => 'en']);

        $this->assertSame($attributeGroup, $event->getAttributeGroup());
        $this->assertSame('restored', $event->getEventType());
        $this->assertSame(['defaultLocale' => 'en'], $event->getEventPayload());
        $this->assertSame(AttributeGroupInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('attributeGroup-uuid', $event->getResourceId());
        $this->assertSame('Name EN', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame(AttributeGroupAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testResourceTitleIsNullWithoutDefaultLocale(): void
    {
        $event = new AttributeGroupRestoredEvent(new AttributeGroup(), []);

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceTitleLocale());
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
