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
use Sulu\Product\Application\Message\ModifyProductComponentsMessage;

#[CoversClass(ModifyProductComponentsMessage::class)]
class ModifyProductComponentsMessageTest extends TestCase
{
    public function testGetters(): void
    {
        $message = new ModifyProductComponentsMessage(['uuid' => 'parent'], 'de', ['a', 'b']);

        $this->assertSame(['uuid' => 'parent'], $message->getIdentifier());
        $this->assertSame('de', $message->getLocale());
        $this->assertSame(['a', 'b'], $message->getComponents());
    }
}
