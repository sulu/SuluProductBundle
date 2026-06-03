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
use Sulu\Product\Domain\Model\AttributeTranslation;

#[CoversClass(AttributeTranslation::class)]
class AttributeTranslationTest extends TestCase
{
    public function testConstructorAssignsValues(): void
    {
        $attribute = new Attribute();
        $translation = new AttributeTranslation($attribute, 'en', 'Color');

        $this->assertSame($attribute, $translation->getAttribute());
        $this->assertSame('en', $translation->getLocale());
        $this->assertSame('Color', $translation->getName());
    }

    public function testSetLocaleIsFluentAndStores(): void
    {
        $translation = new AttributeTranslation(new Attribute(), 'en', 'Color');

        $this->assertSame($translation, $translation->setLocale('de'));
        $this->assertSame('de', $translation->getLocale());
    }

    public function testSetNameIsFluentAndStores(): void
    {
        $translation = new AttributeTranslation(new Attribute(), 'en', 'Color');

        $this->assertSame($translation, $translation->setName('Farbe'));
        $this->assertSame('Farbe', $translation->getName());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new AttributeTranslation(new Attribute(), 'en', 'Color');
        $ref = new \ReflectionProperty(AttributeTranslation::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
