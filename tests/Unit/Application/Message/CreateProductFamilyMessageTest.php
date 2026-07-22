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
use Sulu\Product\Application\Message\CreateProductFamilyMessage;

#[CoversClass(CreateProductFamilyMessage::class)]
class CreateProductFamilyMessageTest extends TestCase
{
    public function testGettersExposeData(): void
    {
        $data = [
            'locale' => 'en',
            'name' => 'My Family',
            'description' => 'desc',
            'attributes' => [5 => ['enabled' => true, 'required' => true, 'variant' => false]],
        ];
        $message = new CreateProductFamilyMessage($data);

        $this->assertSame('en', $message->getLocale());
        $this->assertSame('My Family', $message->getName());
        $this->assertSame('desc', $message->getDescription());
        $this->assertSame([5 => ['enabled' => true, 'required' => true, 'variant' => false]], $message->getAttributes());
        $this->assertSame($data, $message->getData());
    }

    public function testDefaultsForOptionalKeys(): void
    {
        $message = new CreateProductFamilyMessage(['locale' => 'en', 'name' => 'X']);
        $this->assertNull($message->getDescription());
        $this->assertSame([], $message->getAttributes());
    }
}
