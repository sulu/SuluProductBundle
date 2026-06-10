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
use Sulu\Product\Domain\Model\AttributeGroupTranslation;

#[CoversClass(AttributeGroup::class)]
class AttributeGroupTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $group = new AttributeGroup();
        $this->assertNull($group->getUuid());
        $this->assertSame([], $group->getGroupAttributes());
        $this->assertNull($group->getTranslation('en'));
    }

    public function testSetUuidIsFluentAndStores(): void
    {
        $group = new AttributeGroup();
        $this->assertSame($group, $group->setUuid('uuid-val'));
        $this->assertSame('uuid-val', $group->getUuid());
    }

    public function testSetExternalIdentifierIsFluentAndStores(): void
    {
        $group = new AttributeGroup();
        $this->assertSame($group, $group->setExternalIdentifier('ext-456'));
        $this->assertSame('ext-456', $group->getExternalIdentifier());
        $group->setExternalIdentifier(null);
        $this->assertNull($group->getExternalIdentifier());
    }

    public function testSetCurrentLocaleUsedByGetTranslation(): void
    {
        $group = new AttributeGroup();
        $this->assertSame($group, $group->setCurrentLocale('de'));
        $t = new AttributeGroupTranslation($group, 'de', 'Gruppe');
        $group->addTranslation($t);
        $this->assertSame($t, $group->getTranslation());
    }

    public function testGetTranslationByExplicitLocale(): void
    {
        $group = new AttributeGroup();
        $en = new AttributeGroupTranslation($group, 'en', 'Group');
        $de = new AttributeGroupTranslation($group, 'de', 'Gruppe');
        $group->addTranslation($en);
        $group->addTranslation($de);
        $this->assertSame($en, $group->getTranslation('en'));
        $this->assertSame($de, $group->getTranslation('de'));
        $this->assertNull($group->getTranslation('fr'));
    }

    public function testAddTranslationDeduplicates(): void
    {
        $group = new AttributeGroup();
        $t = new AttributeGroupTranslation($group, 'en', 'Group');
        $group->addTranslation($t);
        $group->addTranslation($t);
        $this->assertSame($t, $group->getTranslation('en'));
    }

    public function testRemoveTranslationIsFluent(): void
    {
        $group = new AttributeGroup();
        $t = new AttributeGroupTranslation($group, 'en', 'Group');
        $group->addTranslation($t);
        $this->assertSame($group, $group->removeTranslation($t));
        $this->assertNull($group->getTranslation('en'));
    }

    public function testGetGroupAttributesSortedByPosition(): void
    {
        $group = new AttributeGroup();
        $a = new AttributeGroupAttribute($group, new Attribute());
        $a->setPosition(1);
        $b = new AttributeGroupAttribute($group, new Attribute());
        $b->setPosition(0);
        $group->addGroupAttribute($a);
        $group->addGroupAttribute($b);
        $sorted = $group->getGroupAttributes();
        $this->assertSame($b, $sorted[0]);
        $this->assertSame($a, $sorted[1]);
    }

    public function testAddGroupAttributeDeduplicates(): void
    {
        $group = new AttributeGroup();
        $a = new AttributeGroupAttribute($group, new Attribute());
        $group->addGroupAttribute($a);
        $group->addGroupAttribute($a);
        $this->assertCount(1, $group->getGroupAttributes());
    }

    public function testRemoveGroupAttributeIsFluent(): void
    {
        $group = new AttributeGroup();
        $a = new AttributeGroupAttribute($group, new Attribute());
        $group->addGroupAttribute($a);
        $this->assertSame($group, $group->removeGroupAttribute($a));
        $this->assertCount(0, $group->getGroupAttributes());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $group = new AttributeGroup();
        $ref = new \ReflectionProperty(AttributeGroup::class, 'id');
        $ref->setValue($group, 7);
        $this->assertSame(7, $group->getId());
    }
}
