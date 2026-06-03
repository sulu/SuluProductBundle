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
use Sulu\Product\Application\Message\RemoveProductMessage;

#[CoversClass(RemoveProductMessage::class)]
class RemoveProductMessageTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = ['uuid' => 'product-123'];

        $message = new RemoveProductMessage($identifier, 'en');

        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testGetLocale(): void
    {
        $message = new RemoveProductMessage(['uuid' => 'product-123'], 'de');

        $this->assertSame('de', $message->getLocale());
    }

    public function testWithEmptyIdentifier(): void
    {
        $message = new RemoveProductMessage([], 'fr');

        $this->assertSame([], $message->getIdentifier());
        $this->assertSame('fr', $message->getLocale());
    }
}
