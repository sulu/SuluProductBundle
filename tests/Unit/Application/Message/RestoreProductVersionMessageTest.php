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
use Sulu\Product\Application\Message\RestoreProductVersionMessage;

#[CoversClass(RestoreProductVersionMessage::class)]
class RestoreProductVersionMessageTest extends TestCase
{
    public function testGetProductIdentifier(): void
    {
        $identifier = ['uuid' => 'product-123'];

        $message = new RestoreProductVersionMessage($identifier, 2, 'en');

        $this->assertSame($identifier, $message->getProductIdentifier());
    }

    public function testGetVersion(): void
    {
        $message = new RestoreProductVersionMessage(['uuid' => 'product-123'], 7, 'en');

        $this->assertSame(7, $message->getVersion());
    }

    public function testGetLocale(): void
    {
        $message = new RestoreProductVersionMessage(['uuid' => 'product-123'], 1, 'de');

        $this->assertSame('de', $message->getLocale());
    }

    public function testGetOptionsDefaultsToEmptyArray(): void
    {
        $message = new RestoreProductVersionMessage(['uuid' => 'product-123'], 1, 'en');

        $this->assertSame([], $message->getOptions());
    }

    public function testGetOptionsWhenProvided(): void
    {
        $options = ['foo' => 'bar', 'baz' => 42];

        $message = new RestoreProductVersionMessage(['uuid' => 'product-123'], 1, 'en', $options);

        $this->assertSame($options, $message->getOptions());
    }
}
