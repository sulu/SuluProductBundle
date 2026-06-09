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
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Model\AttributeSetTranslation;

#[CoversClass(AttributeSetTranslation::class)]
class AttributeSetTranslationTest extends TestCase
{
    public function testConstructorStoresValues(): void
    {
        $set = new AttributeSet();
        $t = new AttributeSetTranslation($set, 'en', 'My Set');
        $this->assertSame('en', $t->getLocale());
        $this->assertSame('My Set', $t->getName());
        $this->assertNull($t->getDescription());
        $this->assertSame($set, $t->getAttributeSet());
    }

    public function testSettersAreFluent(): void
    {
        $set = new AttributeSet();
        $t = new AttributeSetTranslation($set, 'en', 'Name');
        $this->assertSame($t, $t->setLocale('de'));
        $this->assertSame($t, $t->setName('New'));
        $this->assertSame($t, $t->setDescription('Desc'));
        $this->assertSame('de', $t->getLocale());
        $this->assertSame('New', $t->getName());
        $this->assertSame('Desc', $t->getDescription());
    }

    public function testSetDescriptionNullable(): void
    {
        $set = new AttributeSet();
        $t = new AttributeSetTranslation($set, 'en', 'Name');
        $t->setDescription('has value');
        $t->setDescription(null);
        $this->assertNull($t->getDescription());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $set = new AttributeSet();
        $t = new AttributeSetTranslation($set, 'en', 'Name');
        $ref = new \ReflectionProperty(AttributeSetTranslation::class, 'id');
        $ref->setValue($t, 15);
        $this->assertSame(15, $t->getId());
    }
}
