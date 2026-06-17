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
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;

#[CoversClass(AttributeOptionTranslation::class)]
class AttributeOptionTranslationTest extends TestCase
{
    public function testConstructorAssignsValues(): void
    {
        $option = new AttributeOption(new Attribute(new AttributeGroup()), 'red');
        $translation = new AttributeOptionTranslation($option, 'en', 'Red');

        $this->assertSame($option, $translation->getAttributeOption());
        $this->assertSame('en', $translation->getLocale());
        $this->assertSame('Red', $translation->getName());
    }

    public function testSetLocaleIsFluentAndStores(): void
    {
        $option = new AttributeOption(new Attribute(new AttributeGroup()), 'red');
        $translation = new AttributeOptionTranslation($option, 'en', 'Red');

        $this->assertSame($translation, $translation->setLocale('de'));
        $this->assertSame('de', $translation->getLocale());
    }

    public function testSetNameIsFluentAndStores(): void
    {
        $option = new AttributeOption(new Attribute(new AttributeGroup()), 'red');
        $translation = new AttributeOptionTranslation($option, 'en', 'Red');

        $this->assertSame($translation, $translation->setName('Rot'));
        $this->assertSame('Rot', $translation->getName());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new AttributeOptionTranslation(new AttributeOption(new Attribute(new AttributeGroup()), 'red'), 'en', 'Red');
        $ref = new \ReflectionProperty(AttributeOptionTranslation::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
