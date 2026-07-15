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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mapper\AttributeMapper;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

#[CoversClass(AttributeMapper::class)]
class AttributeMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    private AttributeMapper $mapper;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $this->mapper = new AttributeMapper($this->attributeRepository->reveal());
    }

    public function testMapCreateAttributeMessage(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'description' => 'Product color',
            'options' => [
                ['type' => 'option', 'key' => 'red', 'name' => 'Red'],
                ['type' => 'option', 'key' => 'blue', 'name' => 'Blue'],
            ],
            'group' => 'group-uuid',
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
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
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

        // position = 0 (default), findNextPositionInGroup returns 1 → newPosition = 0 = oldPosition → no change
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
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
        $this->assertSame('text', $attribute->getType()); // type is immutable after creation, mapper does not update it
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

    public function testMapAttributeDataWithLocalizedFlag(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'weight',
            'type' => 'number',
            'name' => 'Weight',
            'localized' => true,
            'group' => 'group-uuid',
        ]));

        $this->assertTrue($attribute->isLocalized());
    }

    public function testMapPersistsUnitInConfig(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'weight',
            'type' => 'number',
            'name' => 'Weight',
            'config' => ['unit' => 'KILOGRAM', 'min' => 0],
            'group' => 'group-uuid',
        ]));

        $this->assertSame(['unit' => 'KILOGRAM', 'min' => 0], $attribute->getConfig());
    }

    public function testMapModifyAttributeMessageLeavesMissingOptionalFieldsUnchanged(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType('text');
        $translation = new AttributeTranslation($attribute, 'en', 'Color');
        $translation->setDescription('Product color');
        $attribute->addTranslation($translation);

        // position = 0 (default), findNextPositionInGroup returns 1 → newPosition = 0 = oldPosition → no change
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
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
