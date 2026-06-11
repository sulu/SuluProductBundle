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

namespace Sulu\Product\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Mapper\AttributeMapper;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;

#[CoversClass(AttributeMapper::class)]
class AttributeMapperTest extends TestCase
{
    public function testMapCreateAttributeMessage(): void
    {
        $attribute = new Attribute();
        $mapper = new AttributeMapper();

        $mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'description' => 'Product color',
            'options' => [
                ['type' => 'option', 'key' => 'red', 'name' => 'Red'],
                ['type' => 'option', 'key' => 'blue', 'name' => 'Blue'],
            ],
        ]));

        $this->assertSame('color', $attribute->getKey());
        $this->assertSame('options', $attribute->getType());

        $translation = $attribute->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('Product color', $translation->getDescription());

        $options = $attribute->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame('red', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('Red', $options[0]->getTranslation('en')?->getName());
        $this->assertSame('blue', $options[1]->getKey());
        $this->assertSame(1, $options[1]->getPosition());
        $this->assertSame('Blue', $options[1]->getTranslation('en')?->getName());
    }

    public function testMapModifyAttributeMessageUpdatesExistingData(): void
    {
        $attribute = new Attribute();
        $attribute->setKey('old-color');
        $attribute->setType('text');
        $translation = new AttributeTranslation($attribute, 'en', 'Old Color');
        $translation->setDescription('Old description');
        $attribute->addTranslation($translation);

        $red = new AttributeOption($attribute, 'red');
        $red->addTranslation(new AttributeOptionTranslation($red, 'en', 'Red'));
        $attribute->addOption($red);

        $blue = new AttributeOption($attribute, 'blue');
        $blue->addTranslation(new AttributeOptionTranslation($blue, 'en', 'Blue'));
        $attribute->addOption($blue);

        $mapper = new AttributeMapper();
        $mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'description' => 'Updated description',
            'options' => [
                ['type' => 'option', 'key' => 'blue', 'name' => 'Azure'],
                ['type' => 'option', 'key' => 'green', 'name' => 'Green'],
            ],
        ]));

        $this->assertSame('color', $attribute->getKey());
        $this->assertSame('options', $attribute->getType());
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('Updated description', $translation->getDescription());

        $options = $attribute->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame('blue', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('Azure', $options[0]->getTranslation('en')?->getName());
        $this->assertSame('green', $options[1]->getKey());
        $this->assertSame(1, $options[1]->getPosition());
        $this->assertSame('Green', $options[1]->getTranslation('en')?->getName());
    }

    public function testMapModifyAttributeMessageLeavesMissingOptionalFieldsUnchanged(): void
    {
        $attribute = new Attribute();
        $attribute->setKey('color');
        $attribute->setType('text');
        $translation = new AttributeTranslation($attribute, 'en', 'Color');
        $translation->setDescription('Product color');
        $attribute->addTranslation($translation);

        $mapper = new AttributeMapper();
        $mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
        ]));

        $this->assertSame('color', $attribute->getKey());
        $this->assertSame('text', $attribute->getType());
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('Product color', $translation->getDescription());
    }
}
