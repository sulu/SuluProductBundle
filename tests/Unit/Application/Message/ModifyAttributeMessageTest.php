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
use Sulu\Product\Application\Message\ModifyAttributeMessage;

#[CoversClass(ModifyAttributeMessage::class)]
class ModifyAttributeMessageTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = ['uuid' => 'attribute-123', 'key' => 'color'];
        $data = ['locale' => 'en', 'key' => 'color', 'type' => 'text', 'name' => 'Color'];

        $message = new ModifyAttributeMessage($identifier, $data);

        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testGetData(): void
    {
        $identifier = ['uuid' => 'attribute-123'];
        $data = ['locale' => 'en', 'key' => 'color', 'type' => 'text', 'name' => 'Color'];

        $message = new ModifyAttributeMessage($identifier, $data);

        $this->assertSame($data, $message->getData());
    }

    public function testTypedGetters(): void
    {
        $options = [
            ['type' => 'option', 'key' => 'red', 'name' => 'Red'],
        ];

        $message = new ModifyAttributeMessage(['key' => 'color'], [
            'locale' => 'en',
            'key' => 'new-color',
            'type' => 'options',
            'name' => 'Color',
            'description' => 'Product color',
            'options' => $options,
        ]);

        $this->assertSame('en', $message->getLocale());
        $this->assertSame('new-color', $message->getKey());
        $this->assertSame('options', $message->getType());
        $this->assertSame('Color', $message->getName());
        $this->assertSame('Product color', $message->getDescription());
        $this->assertSame($options, $message->getOptions());
    }

    public function testMissingOptionalTypedGettersReturnNull(): void
    {
        $message = new ModifyAttributeMessage(
            ['uuid' => 'attribute-123'],
            ['locale' => 'en', 'key' => 'color', 'type' => 'text', 'name' => 'Color'],
        );

        $this->assertNull($message->getDescription());
        $this->assertNull($message->getOptions());
    }
}
