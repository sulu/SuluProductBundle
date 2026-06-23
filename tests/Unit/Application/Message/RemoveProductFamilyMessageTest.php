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
use Sulu\Product\Application\Message\RemoveProductFamilyMessage;

#[CoversClass(RemoveProductFamilyMessage::class)]
class RemoveProductFamilyMessageTest extends TestCase
{
    public function testGetUuid(): void
    {
        $this->assertSame('uuid-1', (new RemoveProductFamilyMessage('uuid-1'))->getUuid());
    }
}
