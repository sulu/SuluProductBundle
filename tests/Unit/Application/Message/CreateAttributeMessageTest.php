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
            'name' => 'Color',
            'group' => 'group-uuid',
        ];

        $message = new CreateAttributeMessage($data);

        $this->assertSame($data, $message->getData());
    }

    public function testTypedGetters(): void
    {
        $options = [
            ['type' => 'option', 'key' => 'red', 'name' => 'Red'],
        ];

        $message = new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'description' => 'Product color',
            'options' => $options,
            'group' => 'group-uuid',
        ]);

        $this->assertSame('en', $message->getLocale());
        $this->assertSame('color', $message->getKey());
        $this->assertSame('options', $message->getType());
        $this->assertSame('Color', $message->getName());
        $this->assertSame('Product color', $message->getDescription());
        $this->assertSame($options, $message->getOptions());
    }

    public function testGetDescriptionReturnsNullWhenMissing(): void
    {
        $message = new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'group' => 'group-uuid',
        ]);

        $this->assertNull($message->getDescription());
    }
}
