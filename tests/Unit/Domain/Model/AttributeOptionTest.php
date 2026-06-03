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
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;

#[CoversClass(AttributeOption::class)]
class AttributeOptionTest extends TestCase
{
    public function testConstructorAssignsAttributeAndKey(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'red');

        $this->assertSame($attribute, $option->getAttribute());
        $this->assertSame('red', $option->getKey());
    }

    public function testConstructorDefaults(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'red');

        $this->assertNull($option->getUuid());
        $this->assertNull($option->getTranslation('en'));
    }

    public function testSetKeyIsFluentAndStores(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'red');

        $this->assertSame($option, $option->setKey('green'));
        $this->assertSame('green', $option->getKey());
    }

    public function testGetTranslationByLocaleAndUnknownReturnsNull(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'red');
        $translation = new AttributeOptionTranslation($option, 'en', 'Red');
        $option->addTranslation($translation);

        $this->assertSame($translation, $option->getTranslation('en'));
        $this->assertNull($option->getTranslation('de'));
    }

    public function testAddTranslationIsFluentAndDeduplicates(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'red');
        $translation = new AttributeOptionTranslation($option, 'en', 'Red');

        $this->assertSame($option, $option->addTranslation($translation));
        $option->addTranslation($translation);

        $this->assertSame($translation, $option->getTranslation('en'));
    }

    public function testRemoveTranslationIsFluent(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'red');
        $translation = new AttributeOptionTranslation($option, 'en', 'Red');
        $option->addTranslation($translation);

        $this->assertSame($option, $option->removeTranslation($translation));
        $this->assertNull($option->getTranslation('en'));
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new AttributeOption(new Attribute(), 'red');
        $ref = new \ReflectionProperty(AttributeOption::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
