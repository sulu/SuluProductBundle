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
use Sulu\Product\Application\Message\CreateAttributeMessage;

#[CoversClass(CreateAttributeMessage::class)]
class CreateAttributeMessageTest extends TestCase
{
    public function testGetData(): void
    {
        $data = [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
        ];

        $message = new CreateAttributeMessage($data);

        $this->assertSame($data, $message->getData());
    }

    public function testConstructorThrowsWhenLocaleMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateAttributeMessage(['key' => 'color']);
    }

    public function testConstructorThrowsWhenKeyMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateAttributeMessage(['locale' => 'en']);
    }

    public function testConstructorThrowsWhenLocaleIsNotString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateAttributeMessage(['locale' => 123, 'key' => 'color']);
    }

    public function testConstructorThrowsWhenKeyIsNotString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateAttributeMessage(['locale' => 'en', 'key' => 42]);
    }
}
