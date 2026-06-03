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
use Sulu\Product\Application\Message\CreateProductMessage;

#[CoversClass(CreateProductMessage::class)]
class CreateProductMessageTest extends TestCase
{
    public function testGetDataWithoutUuid(): void
    {
        $data = ['locale' => 'en', 'name' => 'foo'];

        $message = new CreateProductMessage($data);

        $this->assertSame($data, $message->getData());
    }

    public function testGetUuidIsNullByDefault(): void
    {
        $message = new CreateProductMessage(['locale' => 'en']);

        $this->assertNull($message->getUuid());
    }

    public function testGetUuidWhenProvided(): void
    {
        $data = ['locale' => 'en', 'uuid' => 'product-123'];

        $message = new CreateProductMessage($data);

        $this->assertSame('product-123', $message->getUuid());
        $this->assertSame($data, $message->getData());
    }

    public function testConstructorThrowsWhenLocaleMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateProductMessage([]);
    }

    public function testConstructorThrowsWhenLocaleIsNotString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateProductMessage(['locale' => 123]);
    }

    public function testConstructorThrowsWhenUuidIsNotString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateProductMessage(['locale' => 'en', 'uuid' => 42]);
    }
}
