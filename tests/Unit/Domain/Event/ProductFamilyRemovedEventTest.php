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
use Sulu\Product\Domain\Event\ProductFamilyRemovedEvent;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

#[CoversClass(ProductFamilyRemovedEvent::class)]
class ProductFamilyRemovedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $event = new ProductFamilyRemovedEvent('productFamily-uuid', 'Name EN', 'en');

        $this->assertSame('removed', $event->getEventType());
        $this->assertSame(ProductFamilyInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('productFamily-uuid', $event->getResourceId());
        $this->assertSame('Name EN', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame(ProductFamilyAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testTitleIsOptional(): void
    {
        $event = new ProductFamilyRemovedEvent('productFamily-uuid', null);

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceTitleLocale());
    }
}
