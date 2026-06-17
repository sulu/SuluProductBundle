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

namespace Sulu\Product\Tests\Unit\Application\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;

#[CoversClass(RemoveAttributeGroupMessage::class)]
class RemoveAttributeGroupMessageTest extends TestCase
{
    public function testConstructorSetsUuid(): void
    {
        $uuid = 'test-uuid-789';
        $message = new RemoveAttributeGroupMessage($uuid);

        $this->assertSame($uuid, $message->getUuid());
    }

    public function testConstructorThrowsWhenUuidIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new RemoveAttributeGroupMessage(123);
    }
}
