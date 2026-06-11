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
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;

#[CoversClass(AttributeGroupTranslation::class)]
class AttributeGroupTranslationTest extends TestCase
{
    public function testConstructorStoresValues(): void
    {
        $group = new AttributeGroup();
        $t = new AttributeGroupTranslation($group, 'en', 'My Group');
        $this->assertSame('en', $t->getLocale());
        $this->assertSame('My Group', $t->getName());
        $this->assertNull($t->getDescription());
        $this->assertSame($group, $t->getGroup());
    }

    public function testSettersAreFluent(): void
    {
        $group = new AttributeGroup();
        $t = new AttributeGroupTranslation($group, 'en', 'Name');
        $this->assertSame($t, $t->setLocale('de'));
        $this->assertSame($t, $t->setName('New'));
        $this->assertSame($t, $t->setDescription('Desc'));
        $this->assertSame('de', $t->getLocale());
        $this->assertSame('New', $t->getName());
        $this->assertSame('Desc', $t->getDescription());
    }

    public function testSetDescriptionNullable(): void
    {
        $group = new AttributeGroup();
        $t = new AttributeGroupTranslation($group, 'en', 'Name');
        $t->setDescription('has value');
        $t->setDescription(null);
        $this->assertNull($t->getDescription());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $group = new AttributeGroup();
        $t = new AttributeGroupTranslation($group, 'en', 'Name');
        $ref = new \ReflectionProperty(AttributeGroupTranslation::class, 'id');
        $ref->setValue($t, 15);
        $this->assertSame(15, $t->getId());
    }
}
