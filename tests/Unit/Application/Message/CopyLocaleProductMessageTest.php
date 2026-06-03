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
use Sulu\Product\Application\Message\CopyLocaleProductMessage;

#[CoversClass(CopyLocaleProductMessage::class)]
class CopyLocaleProductMessageTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $message = new CopyLocaleProductMessage($identifier, 'en', 'de');
        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testGetSourceLocale(): void
    {
        $message = new CopyLocaleProductMessage(['uuid' => 'product-123'], 'en', 'de');
        $this->assertSame('en', $message->getSourceLocale());
    }

    public function testGetTargetLocale(): void
    {
        $message = new CopyLocaleProductMessage(['uuid' => 'product-123'], 'en', 'de');
        $this->assertSame('de', $message->getTargetLocale());
    }

    public function testWithEmptyIdentifier(): void
    {
        $message = new CopyLocaleProductMessage([], 'fr', 'es');
        $this->assertSame([], $message->getIdentifier());
        $this->assertSame('fr', $message->getSourceLocale());
        $this->assertSame('es', $message->getTargetLocale());
    }
}
