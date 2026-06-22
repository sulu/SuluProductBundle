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
use Sulu\Product\Application\Message\CreateProductMessage;

#[CoversClass(CreateProductMessage::class)]
class CreateProductMessageTest extends TestCase
{
    public function testGetDataWithoutUuid(): void
    {
        $data = ['locale' => 'en', 'productFamily' => 'family-uuid'];

        $message = new CreateProductMessage($data);

        $this->assertSame($data, $message->getData());
    }

    public function testGetUuidIsNullByDefault(): void
    {
        $message = new CreateProductMessage(['locale' => 'en', 'productFamily' => 'family-uuid']);

        $this->assertNull($message->getUuid());
    }

    public function testGetUuidWhenProvided(): void
    {
        $data = ['locale' => 'en', 'productFamily' => 'family-uuid', 'uuid' => 'product-123'];

        $message = new CreateProductMessage($data);

        $this->assertSame('product-123', $message->getUuid());
        $this->assertSame($data, $message->getData());
    }

    public function testGetLocale(): void
    {
        $message = new CreateProductMessage(['locale' => 'en', 'productFamily' => 'family-uuid']);

        $this->assertSame('en', $message->getLocale());
    }

    public function testGetProductFamily(): void
    {
        $message = new CreateProductMessage(['locale' => 'en', 'productFamily' => 'family-uuid']);

        $this->assertSame('family-uuid', $message->getProductFamily());
    }

    public function testGetAttributes(): void
    {
        $message = new CreateProductMessage(['locale' => 'en', 'productFamily' => 'fam-1', 'attributes' => [7 => 42.0]]);

        $this->assertSame([7 => 42.0], $message->getAttributes());
    }

    public function testGetAttributesDefaultsToEmpty(): void
    {
        $message = new CreateProductMessage(['locale' => 'en', 'productFamily' => 'fam-1']);

        $this->assertSame([], $message->getAttributes());
    }
}
