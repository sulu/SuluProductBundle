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
use Sulu\Product\Application\Message\ModifyAttributeSetMessage;

#[CoversClass(ModifyAttributeSetMessage::class)]
class ModifyAttributeSetMessageTest extends TestCase
{
    public function testConstructorWithRequiredFields(): void
    {
        $message = new ModifyAttributeSetMessage(
            uuid: 'test-uuid-123',
            locale: 'en',
            name: 'Test Set',
        );

        $this->assertSame('test-uuid-123', $message->getUuid());
        $this->assertSame('en', $message->getLocale());
        $this->assertSame('Test Set', $message->getName());
        $this->assertNull($message->getDescription());
        $this->assertSame([], $message->getAttributes());
    }

    public function testConstructorWithAllFields(): void
    {
        $attributes = [
            ['attribute' => 'uuid-1', 'required' => true],
            ['attribute' => 'uuid-2', 'required' => false],
        ];

        $message = new ModifyAttributeSetMessage(
            uuid: 'test-uuid-456',
            locale: 'de',
            name: 'Advanced Set',
            description: 'A test description',
            attributes: $attributes,
        );

        $this->assertSame('test-uuid-456', $message->getUuid());
        $this->assertSame('de', $message->getLocale());
        $this->assertSame('Advanced Set', $message->getName());
        $this->assertSame('A test description', $message->getDescription());
        $this->assertSame($attributes, $message->getAttributes());
    }

    public function testConstructorThrowsWhenUuidIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new ModifyAttributeSetMessage(uuid: 789, locale: 'en', name: 'Test Set');
    }

    public function testConstructorThrowsWhenLocaleIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new ModifyAttributeSetMessage(uuid: 'test-uuid', locale: 456, name: 'Test Set');
    }

    public function testConstructorThrowsWhenNameIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new ModifyAttributeSetMessage(uuid: 'test-uuid', locale: 'en', name: 123);
    }

    public function testConstructorThrowsWhenDescriptionIsNotStringOrNull(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new ModifyAttributeSetMessage(uuid: 'test-uuid', locale: 'en', name: 'Test Set', description: 456);
    }
}
