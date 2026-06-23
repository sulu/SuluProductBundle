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
        $data = ['locale' => 'en'];

        $message = new ModifyProductMessage($identifier, $data);

        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testGetData(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $data = ['locale' => 'en'];

        $message = new ModifyProductMessage($identifier, $data);

        $this->assertSame($data, $message->getData());
    }

    public function testGetLocale(): void
    {
        $message = new ModifyProductMessage(['uuid' => 'product-123'], ['locale' => 'en']);

        $this->assertSame('en', $message->getLocale());
    }

    public function testGetProductFamilyIsNullByDefault(): void
    {
        $message = new ModifyProductMessage(['uuid' => 'product-123'], ['locale' => 'en']);

        $this->assertNull($message->getProductFamily());
    }

    public function testGetProductFamilyWhenProvided(): void
    {
        $message = new ModifyProductMessage(
            ['uuid' => 'product-123'],
            ['locale' => 'en', 'productFamily' => 'family-uuid'],
        );

        $this->assertSame('family-uuid', $message->getProductFamily());
    }
}
