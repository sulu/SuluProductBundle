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
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;

#[CoversClass(ProductFamily::class)]
class ProductFamilyTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $family = new ProductFamily();
        $this->assertNull($family->getUuid());
        $this->assertSame([], $family->getFamilyAttributes());
        $this->assertNull($family->getTranslation('en'));
    }

    public function testSetUuidIsFluentAndStores(): void
    {
        $family = new ProductFamily();
        $this->assertSame($family, $family->setUuid('uuid-val'));
        $this->assertSame('uuid-val', $family->getUuid());
    }

    public function testSetExternalIdentifierIsFluentAndStores(): void
    {
        $family = new ProductFamily();
        $this->assertSame($family, $family->setExternalIdentifier('ext-1'));
        $this->assertSame('ext-1', $family->getExternalIdentifier());
        $family->setExternalIdentifier(null);
        $this->assertNull($family->getExternalIdentifier());
    }

    public function testGetTranslationByExplicitLocale(): void
    {
        $family = new ProductFamily();
        $en = new ProductFamilyTranslation($family, 'en', 'Family');
        $de = new ProductFamilyTranslation($family, 'de', 'Familie');
        $family->addTranslation($en)->addTranslation($de);
        $this->assertSame($en, $family->getTranslation('en'));
        $this->assertSame($de, $family->getTranslation('de'));
        $this->assertNull($family->getTranslation('fr'));
    }

    public function testAddTranslationDeduplicatesAndRemoveIsFluent(): void
    {
        $family = new ProductFamily();
        $t = new ProductFamilyTranslation($family, 'en', 'Family');
        $family->addTranslation($t)->addTranslation($t);
        $this->assertSame($t, $family->getTranslation('en'));
        $this->assertSame($family, $family->removeTranslation($t));
        $this->assertNull($family->getTranslation('en'));
    }

    public function testFamilyAttributesAddDeduplicatesAndRemove(): void
    {
        $family = new ProductFamily();
        $fa = new ProductFamilyAttribute($family, new Attribute(new AttributeGroup()));
        $family->addFamilyAttribute($fa)->addFamilyAttribute($fa);
        $this->assertCount(1, $family->getFamilyAttributes());
        $this->assertSame($family, $family->removeFamilyAttribute($fa));
        $this->assertCount(0, $family->getFamilyAttributes());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $family = new ProductFamily();
        $ref = new \ReflectionProperty(ProductFamily::class, 'id');
        $ref->setValue($family, 9);
        $this->assertSame(9, $family->getId());
    }

    public function testImplementsAuditableInterface(): void
    {
        $family = new ProductFamily();
        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(AuditableInterface::class, $family);
    }

    public function testGetTranslationsReturnsAllTranslations(): void
    {
        $family = new ProductFamily();
        $en = new ProductFamilyTranslation($family, 'en', 'Family');
        $de = new ProductFamilyTranslation($family, 'de', 'Familie');
        $family->addTranslation($en)->addTranslation($de);
        $translations = \iterator_to_array($family->getTranslations());
        $this->assertCount(2, $translations);
    }
}
