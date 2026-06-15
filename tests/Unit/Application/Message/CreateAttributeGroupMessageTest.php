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
    public function testGettersWithRequiredFields(): void
    {
        $message = new CreateAttributeGroupMessage([
            'locale' => 'en',
            'name' => 'Test Group',
        ]);

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

        $message = new CreateAttributeGroupMessage($data);

        $this->assertSame('de', $message->getLocale());
        $this->assertSame('Advanced Group', $message->getName());
        $this->assertSame('A test description', $message->getDescription());
        $this->assertSame($attributes, $message->getAttributes());
    }

    public function testGetDataReturnsFullArray(): void
    {
        $data = [
            'locale' => 'en',
            'name' => 'Test Group',
            'description' => 'A description',
        ];

        $message = new CreateAttributeGroupMessage($data);

        $this->assertSame($data, $message->getData());
    }

    public function testGetDescriptionReturnsNullWhenMissing(): void
    {
        $message = new CreateAttributeGroupMessage([
            'locale' => 'en',
            'name' => 'Test Group',
        ]);

        $this->assertNull($message->getDescription());
    }

    public function testGetAttributesReturnsEmptyArrayWhenMissing(): void
    {
        $message = new CreateAttributeGroupMessage([
            'locale' => 'en',
            'name' => 'Test Group',
        ]);

        $this->assertSame([], $message->getAttributes());
    }
}
