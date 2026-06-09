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
use Sulu\Product\Application\Message\CreateAttributeSetMessage;

#[CoversClass(CreateAttributeSetMessage::class)]
class CreateAttributeSetMessageTest extends TestCase
{
    public function testConstructorWithRequiredFields(): void
    {
        $message = new CreateAttributeSetMessage(
            locale: 'en',
            name: 'Test Set',
        );

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

        $message = new CreateAttributeSetMessage(
            locale: 'de',
            name: 'Advanced Set',
            description: 'A test description',
            attributes: $attributes,
        );

        $this->assertSame('de', $message->getLocale());
        $this->assertSame('Advanced Set', $message->getName());
        $this->assertSame('A test description', $message->getDescription());
        $this->assertSame($attributes, $message->getAttributes());
    }

    public function testConstructorThrowsWhenLocaleIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new CreateAttributeSetMessage(locale: 123, name: 'Test Set');
    }

    public function testConstructorThrowsWhenNameIsNotString(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new CreateAttributeSetMessage(locale: 'en', name: 456);
    }

    public function testConstructorThrowsWhenDescriptionIsNotStringOrNull(): void
    {
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore argument.type, new.resultUnused */
        new CreateAttributeSetMessage(locale: 'en', name: 'Test Set', description: 123);
    }
}
