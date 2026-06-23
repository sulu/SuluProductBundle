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
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;

#[CoversClass(ProductFamilyTranslation::class)]
class ProductFamilyTranslationTest extends TestCase
{
    public function testConstructorStoresValues(): void
    {
        $family = new ProductFamily();
        $t = new ProductFamilyTranslation($family, 'en', 'Family');
        $this->assertSame($family, $t->getFamily());
        $this->assertSame('en', $t->getLocale());
        $this->assertSame('Family', $t->getName());
        $this->assertNull($t->getDescription());
    }

    public function testSettersAreFluent(): void
    {
        $t = new ProductFamilyTranslation(new ProductFamily(), 'en', 'Family');
        $this->assertSame($t, $t->setLocale('de'));
        $this->assertSame('de', $t->getLocale());
        $this->assertSame($t, $t->setName('Familie'));
        $this->assertSame('Familie', $t->getName());
        $this->assertSame($t, $t->setDescription('desc'));
        $this->assertSame('desc', $t->getDescription());
        $t->setDescription(null);
        $this->assertNull($t->getDescription());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $t = new ProductFamilyTranslation(new ProductFamily(), 'en', 'Family');
        $ref = new \ReflectionProperty(ProductFamilyTranslation::class, 'id');
        $ref->setValue($t, 3);
        $this->assertSame(3, $t->getId());
    }
}
