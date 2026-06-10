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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;

#[CoversClass(AttributeGroupAttribute::class)]
class AttributeGroupAttributeTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute();
        $ga = new AttributeGroupAttribute($group, $attribute);
        $this->assertSame(0, $ga->getPosition());
        $this->assertSame($group, $ga->getAttributeGroup());
        $this->assertSame($attribute, $ga->getAttribute());
    }

    public function testSetPositionIsFluent(): void
    {
        $group = new AttributeGroup();
        $ga = new AttributeGroupAttribute($group, new Attribute());
        $this->assertSame($ga, $ga->setPosition(3));
        $this->assertSame(3, $ga->getPosition());
    }

    public function testSetAttributeIsFluent(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute();
        $ga = new AttributeGroupAttribute($group, new Attribute());
        $this->assertSame($ga, $ga->setAttribute($attribute));
        $this->assertSame($attribute, $ga->getAttribute());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $group = new AttributeGroup();
        $ga = new AttributeGroupAttribute($group, new Attribute());
        $ref = new \ReflectionProperty(AttributeGroupAttribute::class, 'id');
        $ref->setValue($ga, 42);
        $this->assertSame(42, $ga->getId());
    }
}
