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
use Sulu\Product\Application\AttributeType\JsonAttributeType;
use Sulu\Product\Domain\Model\AttributeInterface;

#[CoversClass(JsonAttributeType::class)]
class JsonAttributeTypeTest extends TestCase
{
    public function testImplementsAttributeTypeInterface(): void
    {
        $interfaces = \class_implements(JsonAttributeType::class);

        $this->assertIsArray($interfaces);
        $this->assertContains(AttributeTypeInterface::class, $interfaces);
    }

    public function testGetKey(): void
    {
        $type = new JsonAttributeType();

        $this->assertSame(AttributeInterface::TYPE_JSON, $type->getKey());
        $this->assertSame('json', $type->getKey());
    }
}
