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
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;

#[CoversClass(ApplyWorkflowTransitionProductMessage::class)]
class ApplyWorkflowTransitionProductMessageTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $message = new ApplyWorkflowTransitionProductMessage($identifier, 'en', 'publish');
        $this->assertSame($identifier, $message->getIdentifier());
    }

    public function testGetLocale(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $message = new ApplyWorkflowTransitionProductMessage($identifier, 'en', 'publish');
        $this->assertSame('en', $message->getLocale());
    }

    public function testGetTransitionName(): void
    {
        $identifier = ['uuid' => 'product-123'];
        $message = new ApplyWorkflowTransitionProductMessage($identifier, 'en', 'publish');
        $this->assertSame('publish', $message->getTransitionName());
    }

    public function testWithEmptyIdentifier(): void
    {
        $message = new ApplyWorkflowTransitionProductMessage([], 'de', 'unpublish');
        $this->assertSame([], $message->getIdentifier());
        $this->assertSame('de', $message->getLocale());
        $this->assertSame('unpublish', $message->getTransitionName());
    }
}
