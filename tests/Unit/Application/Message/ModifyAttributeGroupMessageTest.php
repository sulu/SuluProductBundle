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
use Sulu\Product\Application\Message\ModifyAttributeGroupMessage;

#[CoversClass(ModifyAttributeGroupMessage::class)]
class ModifyAttributeGroupMessageTest extends TestCase
{
    public function testGettersWithRequiredFields(): void
    {
        $message = new ModifyAttributeGroupMessage(
            ['uuid' => 'test-uuid-123'],
            ['locale' => 'en', 'name' => 'Test Group'],
        );

        $this->assertSame('test-uuid-123', $message->getUuid());
        $this->assertSame(['uuid' => 'test-uuid-123'], $message->getIdentifier());
        $this->assertSame('en', $message->getLocale());
        $this->assertSame('Test Group', $message->getName());
        $this->assertNull($message->getDescription());
        $this->assertSame([], $message->getAttributes());
    }

    public function testGettersWithAllFields(): void
    {
        $attributes = [
            ['attribute' => 'uuid-1'],
            ['attribute' => 'uuid-2'],
        ];

        $data = [
            'locale' => 'de',
            'name' => 'Advanced Group',
            'description' => 'A test description',
            'attributes' => $attributes,
        ];

        $message = new ModifyAttributeGroupMessage(['uuid' => 'test-uuid-456'], $data);

        $this->assertSame('test-uuid-456', $message->getUuid());
        $this->assertSame('de', $message->getLocale());
        $this->assertSame('Advanced Group', $message->getName());
        $this->assertSame('A test description', $message->getDescription());
        $this->assertSame($attributes, $message->getAttributes());
    }

    public function testGetDataReturnsFullDataArray(): void
    {
        $data = ['locale' => 'en', 'name' => 'Test Group'];

        $message = new ModifyAttributeGroupMessage(['uuid' => 'test-uuid'], $data);

        $this->assertSame($data, $message->getData());
    }

    public function testGetDescriptionReturnsNullWhenMissing(): void
    {
        $message = new ModifyAttributeGroupMessage(
            ['uuid' => 'test-uuid'],
            ['locale' => 'en', 'name' => 'Test Group'],
        );

        $this->assertNull($message->getDescription());
    }

    public function testGetAttributesReturnsEmptyArrayWhenMissing(): void
    {
        $message = new ModifyAttributeGroupMessage(
            ['uuid' => 'test-uuid'],
            ['locale' => 'en', 'name' => 'Test Group'],
        );

        $this->assertSame([], $message->getAttributes());
    }
}
