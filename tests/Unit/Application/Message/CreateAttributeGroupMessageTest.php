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
use Sulu\Product\Application\Message\CreateAttributeGroupMessage;

#[CoversClass(CreateAttributeGroupMessage::class)]
class CreateAttributeGroupMessageTest extends TestCase
{
    public function testConstructorWithRequiredFields(): void
    {
        $message = new CreateAttributeGroupMessage(
            locale: 'en',
            name: 'Test Group',
        );

        $this->assertSame('en', $message->getLocale());
        $this->assertSame('Test Group', $message->getName());
        $this->assertNull($message->getDescription());
        $this->assertSame([], $message->getAttributes());
    }

    public function testConstructorWithAllFields(): void
    {
        $attributes = [
            ['attribute' => 'uuid-1'],
            ['attribute' => 'uuid-2'],
        ];

        $message = new CreateAttributeGroupMessage(
            locale: 'de',
            name: 'Advanced Group',
            description: 'A test description',
            attributes: $attributes,
        );

        $this->assertSame('de', $message->getLocale());
        $this->assertSame('Advanced Group', $message->getName());
        $this->assertSame('A test description', $message->getDescription());
        $this->assertSame($attributes, $message->getAttributes());
    }

    public function testConstructorThrowsWhenLocaleIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new CreateAttributeGroupMessage(locale: 123, name: 'Test Group');
    }

    public function testConstructorThrowsWhenNameIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new CreateAttributeGroupMessage(locale: 'en', name: 456);
    }

    public function testConstructorThrowsWhenDescriptionIsNotStringOrNull(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new CreateAttributeGroupMessage(locale: 'en', name: 'Test Group', description: 123);
    }
}
