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
use Sulu\Product\Domain\Event\AttributeGroupRemovedEvent;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;

#[CoversClass(AttributeGroupRemovedEvent::class)]
class AttributeGroupRemovedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $event = new AttributeGroupRemovedEvent('attributeGroup-uuid', 'Name EN', 'en');

        $this->assertSame('removed', $event->getEventType());
        $this->assertSame(AttributeGroupInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('attributeGroup-uuid', $event->getResourceId());
        $this->assertSame('Name EN', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame(AttributeGroupAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testTitleIsOptional(): void
    {
        $event = new AttributeGroupRemovedEvent('attributeGroup-uuid', null);

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceTitleLocale());
    }
}
