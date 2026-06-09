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
use Sulu\Product\Domain\Model\AttributeSetTranslation;

#[CoversClass(AttributeSet::class)]
class AttributeSetTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $set = new AttributeSet();
        $this->assertNull($set->getUuid());
        $this->assertSame([], $set->getSetAttributes());
        $this->assertNull($set->getTranslation('en'));
    }

    public function testSetUuidIsFluentAndStores(): void
    {
        $set = new AttributeSet();
        $this->assertSame($set, $set->setUuid('uuid-val'));
        $this->assertSame('uuid-val', $set->getUuid());
    }

    public function testSetExternalIdentifierIsFluentAndStores(): void
    {
        $set = new AttributeSet();
        $this->assertSame($set, $set->setExternalIdentifier('ext-456'));
        $this->assertSame('ext-456', $set->getExternalIdentifier());
        $set->setExternalIdentifier(null);
        $this->assertNull($set->getExternalIdentifier());
    }

    public function testSetCurrentLocaleUsedByGetTranslation(): void
    {
        $set = new AttributeSet();
        $this->assertSame($set, $set->setCurrentLocale('de'));
        $t = new AttributeSetTranslation($set, 'de', 'Paket');
        $set->addTranslation($t);
        $this->assertSame($t, $set->getTranslation());
    }

    public function testGetTranslationByExplicitLocale(): void
    {
        $set = new AttributeSet();
        $en = new AttributeSetTranslation($set, 'en', 'Set');
        $de = new AttributeSetTranslation($set, 'de', 'Satz');
        $set->addTranslation($en);
        $set->addTranslation($de);
        $this->assertSame($en, $set->getTranslation('en'));
        $this->assertSame($de, $set->getTranslation('de'));
        $this->assertNull($set->getTranslation('fr'));
    }

    public function testAddTranslationDeduplicates(): void
    {
        $set = new AttributeSet();
        $t = new AttributeSetTranslation($set, 'en', 'Set');
        $set->addTranslation($t);
        $set->addTranslation($t);
        $this->assertSame($t, $set->getTranslation('en'));
    }

    public function testRemoveTranslationIsFluent(): void
    {
        $set = new AttributeSet();
        $t = new AttributeSetTranslation($set, 'en', 'Set');
        $set->addTranslation($t);
        $this->assertSame($set, $set->removeTranslation($t));
        $this->assertNull($set->getTranslation('en'));
    }

    public function testGetSetAttributesSortedByPosition(): void
    {
        $set = new AttributeSet();
        $a = new AttributeSetAttribute($set, new Attribute());
        $a->setPosition(1);
        $b = new AttributeSetAttribute($set, new Attribute());
        $b->setPosition(0);
        $set->addSetAttribute($a);
        $set->addSetAttribute($b);
        $sorted = $set->getSetAttributes();
        $this->assertSame($b, $sorted[0]);
        $this->assertSame($a, $sorted[1]);
    }

    public function testAddSetAttributeDeduplicates(): void
    {
        $set = new AttributeSet();
        $a = new AttributeSetAttribute($set, new Attribute());
        $set->addSetAttribute($a);
        $set->addSetAttribute($a);
        $this->assertCount(1, $set->getSetAttributes());
    }

    public function testRemoveSetAttributeIsFluent(): void
    {
        $set = new AttributeSet();
        $a = new AttributeSetAttribute($set, new Attribute());
        $set->addSetAttribute($a);
        $this->assertSame($set, $set->removeSetAttribute($a));
        $this->assertCount(0, $set->getSetAttributes());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $set = new AttributeSet();
        $ref = new \ReflectionProperty(AttributeSet::class, 'id');
        $ref->setValue($set, 7);
        $this->assertSame(7, $set->getId());
    }
}
