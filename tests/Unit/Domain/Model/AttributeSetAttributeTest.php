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
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Model\AttributeSetAttribute;

#[CoversClass(AttributeSetAttribute::class)]
class AttributeSetAttributeTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $set = new AttributeSet();
        $attribute = new Attribute();
        $sa = new AttributeSetAttribute($set, $attribute);
        $this->assertFalse($sa->getRequired());
        $this->assertSame(0, $sa->getPosition());
        $this->assertSame($set, $sa->getAttributeSet());
        $this->assertSame($attribute, $sa->getAttribute());
    }

    public function testSetRequiredIsFluent(): void
    {
        $set = new AttributeSet();
        $sa = new AttributeSetAttribute($set, new Attribute());
        $this->assertSame($sa, $sa->setRequired(true));
        $this->assertTrue($sa->getRequired());
    }

    public function testSetPositionIsFluent(): void
    {
        $set = new AttributeSet();
        $sa = new AttributeSetAttribute($set, new Attribute());
        $this->assertSame($sa, $sa->setPosition(3));
        $this->assertSame(3, $sa->getPosition());
    }

    public function testSetAttributeIsFluent(): void
    {
        $set = new AttributeSet();
        $attribute = new Attribute();
        $sa = new AttributeSetAttribute($set, new Attribute());
        $this->assertSame($sa, $sa->setAttribute($attribute));
        $this->assertSame($attribute, $sa->getAttribute());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $set = new AttributeSet();
        $sa = new AttributeSetAttribute($set, new Attribute());
        $ref = new \ReflectionProperty(AttributeSetAttribute::class, 'id');
        $ref->setValue($sa, 42);
        $this->assertSame(42, $sa->getId());
    }
}
