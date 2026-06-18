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
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;

#[CoversClass(ModifyProductFamilyMessage::class)]
class ModifyProductFamilyMessageTest extends TestCase
{
    public function testGettersExposeIdentifierAndData(): void
    {
        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            ['locale' => 'en', 'name' => 'X', 'familyAttributes' => [['attribute' => 1, 'required' => false]]],
        );

        $this->assertSame('family-uuid', $message->getUuid());
        $this->assertSame(['uuid' => 'family-uuid'], $message->getIdentifier());
        $this->assertSame('en', $message->getLocale());
        $this->assertSame('X', $message->getName());
        $this->assertNull($message->getDescription());
        $this->assertSame([['attribute' => 1, 'required' => false]], $message->getFamilyAttributes());
        $this->assertSame(['locale' => 'en', 'name' => 'X', 'familyAttributes' => [['attribute' => 1, 'required' => false]]], $message->getData());
    }
}
