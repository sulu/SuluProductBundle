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
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Model\AttributeInterface;

#[CoversClass(AttributeTypeRegistry::class)]
class AttributeTypeRegistryTest extends TestCase
{
    private function registry(): AttributeTypeRegistry
    {
        return new AttributeTypeRegistry([new NumberAttributeType(), new TextAttributeType()]);
    }

    public function testGetReturnsTypeByKey(): void
    {
        self::assertInstanceOf(NumberAttributeType::class, $this->registry()->get(AttributeInterface::TYPE_NUMBER));
    }

    public function testHas(): void
    {
        self::assertTrue($this->registry()->has(AttributeInterface::TYPE_TEXT));
        self::assertFalse($this->registry()->has('unknown'));
    }

    public function testGetUnknownThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No attribute type registered for key "unknown".');
        $this->registry()->get('unknown');
    }
}
