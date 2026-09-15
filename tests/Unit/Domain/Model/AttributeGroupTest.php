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
use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(AttributeGroup::class)]
class AttributeGroupTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $group = new AttributeGroup();
        $this->assertTrue(Uuid::isValid($group->getUuid()));
        $this->assertNull($group->getTranslation('en'));
    }

    public function testConstructorGeneratesUuidV7WhenNoneProvided(): void
    {
        $group = new AttributeGroup();

        $this->assertTrue(Uuid::isValid($group->getUuid()));
        $this->assertInstanceOf(UuidV7::class, Uuid::fromString($group->getUuid()));
    }

    public function testConstructorAcceptsProvidedUuid(): void
    {
        $uuid = Uuid::v7()->toRfc4122();

        $this->assertSame($uuid, (new AttributeGroup($uuid))->getUuid());
    }

    public function testSetExternalIdentifierIsFluentAndStores(): void
    {
        $group = new AttributeGroup();
        $this->assertSame($group, $group->setExternalIdentifier('ext-456'));
        $this->assertSame('ext-456', $group->getExternalIdentifier());
        $group->setExternalIdentifier(null);
        $this->assertNull($group->getExternalIdentifier());
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

    public function testImplementsAuditableInterface(): void
    {
        $group = new AttributeGroup();
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(AuditableInterface::class, $group);
    }
}
