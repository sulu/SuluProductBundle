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
use Sulu\Product\Application\Message\ApplyProductComponentTransitionMessage;

#[CoversClass(ApplyProductComponentTransitionMessage::class)]
class ApplyProductComponentTransitionMessageTest extends TestCase
{
    public function testGetters(): void
    {
        $message = new ApplyProductComponentTransitionMessage(['uuid' => 'parent'], 'de', 'component', 'publish');

        $this->assertSame(['uuid' => 'parent'], $message->getIdentifier());
        $this->assertSame('de', $message->getLocale());
        $this->assertSame('component', $message->getComponentUuid());
        $this->assertSame('publish', $message->getTransitionName());
    }
}
