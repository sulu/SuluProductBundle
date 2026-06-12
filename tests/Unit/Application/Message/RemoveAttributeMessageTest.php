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
use Sulu\Product\Application\Message\RemoveAttributeMessage;

#[CoversClass(RemoveAttributeMessage::class)]
class RemoveAttributeMessageTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = [
            'uuid' => 'attribute-123',
            'key' => 'color',
        ];

        $message = new RemoveAttributeMessage($identifier);

        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testWithEmptyIdentifier(): void
    {
        $message = new RemoveAttributeMessage([]);

        $this->assertSame([], $message->getIdentifier());
    }
}
