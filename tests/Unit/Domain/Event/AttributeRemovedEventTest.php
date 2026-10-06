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
use Sulu\Product\Domain\Event\AttributeRemovedEvent;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

#[CoversClass(AttributeRemovedEvent::class)]
class AttributeRemovedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $event = new AttributeRemovedEvent('attribute-uuid', 'Name EN', 'en');

        $this->assertSame('removed', $event->getEventType());
        $this->assertSame(AttributeInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('attribute-uuid', $event->getResourceId());
        $this->assertSame('Name EN', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame(AttributeAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testTitleIsOptional(): void
    {
        $event = new AttributeRemovedEvent('attribute-uuid', null);

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceTitleLocale());
    }
}
