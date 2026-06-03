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
use Sulu\Product\Application\Message\ModifyProductMessage;

#[CoversClass(ModifyProductMessage::class)]
class ModifyProductMessageTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $data = ['locale' => 'en', 'name' => 'foo'];

        $message = new ModifyProductMessage($identifier, $data);

        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testGetData(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $data = ['locale' => 'en', 'name' => 'foo'];

        $message = new ModifyProductMessage($identifier, $data);

        $this->assertSame($data, $message->getData());
    }

    public function testConstructorThrowsWhenLocaleMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ModifyProductMessage(['uuid' => 'product-123'], ['name' => 'foo']);
    }

    public function testConstructorThrowsWhenLocaleIsNotString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ModifyProductMessage(['uuid' => 'product-123'], ['locale' => 123]);
    }
}
