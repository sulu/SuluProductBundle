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

namespace Sulu\Product\Tests\Unit\Application\AttributeType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Model\AttributeInterface;

#[CoversClass(TextAttributeType::class)]
class TextAttributeTypeTest extends TestCase
{
    public function testImplementsAttributeTypeInterface(): void
    {
        $interfaces = \class_implements(TextAttributeType::class);

        $this->assertIsArray($interfaces);
        $this->assertContains(AttributeTypeInterface::class, $interfaces);
    }

    public function testGetKey(): void
    {
        $type = new TextAttributeType();

        $this->assertSame(AttributeInterface::TYPE_TEXT, $type->getKey());
        $this->assertSame('text', $type->getKey());
    }
}
