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
use Sulu\Product\Application\AttributeType\MediaAttributeType;
use Sulu\Product\Domain\Model\AttributeInterface;

#[CoversClass(MediaAttributeType::class)]
class MediaAttributeTypeTest extends TestCase
{
    public function testImplementsAttributeTypeInterface(): void
    {
        $interfaces = \class_implements(MediaAttributeType::class);

        $this->assertIsArray($interfaces);
        $this->assertContains(AttributeTypeInterface::class, $interfaces);
    }

    public function testGetKey(): void
    {
        $type = new MediaAttributeType();

        $this->assertSame(AttributeInterface::TYPE_MEDIA, $type->getKey());
        $this->assertSame('media', $type->getKey());
    }
}
