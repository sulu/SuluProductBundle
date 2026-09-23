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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mapper\AttributeMapper;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Domain\Exception\AttributeOptionKeyNotUniqueException;
use Sulu\Product\Domain\Exception\InvalidDateDisplayFormatException;
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

        $this->createOption($attribute, 1, 'red', 'Red');
        $blue = $this->createOption($attribute, 2, 'blue', 'Blue');

        // position = 0 (default), findNextPositionInGroup returns 1 → newPosition = 0 = oldPosition → no change
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'description' => 'Updated description',
            'options' => [
                ['id' => 2, 'type' => 'option', 'key' => 'blue', 'name' => 'Azure'],
                ['type' => 'option', 'key' => 'green', 'name' => 'Green'],
            ],
        ]));

        $this->assertSame('color', $attribute->getKey());
        $this->assertSame('text', $attribute->getType()); // type is immutable after creation, mapper does not update it
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('Updated description', $translation->getDescription());

        $options = $attribute->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame($blue, $options[0]);
        $this->assertSame('blue', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('Azure', $options[0]->getTranslation('en')?->getName());
        $this->assertSame('green', $options[1]->getKey());
        $this->assertSame(1, $options[1]->getPosition());
        $this->assertSame('Green', $options[1]->getTranslation('en')?->getName());
    }

    public function testMapModifyAttributeMessageRenamesOptionMatchedById(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setType('options');

        $blue = $this->createOption($attribute, 7, 'blue', 'Blue');
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'options' => [
                ['id' => 7, 'type' => 'option', 'key' => 'navy', 'name' => 'Navy'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame($blue, $options[0]);
        $this->assertSame('navy', $blue->getKey());
        $this->assertSame('Navy', $blue->getTranslation('en')?->getName());
    }

    public function testMapModifyAttributeMessageCreatesNewOptionForDuplicatedId(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setType('options');

        $blue = $this->createOption($attribute, 7, 'blue', 'Blue');
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'options' => [
                ['id' => 7, 'type' => 'option', 'key' => 'blue', 'name' => 'Blue'],
                ['id' => 7, 'type' => 'option', 'key' => 'navy', 'name' => 'Navy'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame($blue, $options[0]);
        $this->assertSame('blue', $blue->getKey());
        $this->assertNotSame($blue, $options[1]);
        $this->assertSame('navy', $options[1]->getKey());
    }

    public function testMapModifyAttributeMessageSwapsOptionKeys(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setType('options');

        $red = $this->createOption($attribute, 1, 'red', 'Red');
        $blue = $this->createOption($attribute, 2, 'blue', 'Blue');
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'options' => [
                ['id' => 1, 'type' => 'option', 'key' => 'blue', 'name' => 'Red'],
                ['id' => 2, 'type' => 'option', 'key' => 'red', 'name' => 'Blue'],
            ],
        ]));

        $this->assertSame([$red, $blue], $attribute->getOptions());
        $this->assertSame('blue', $red->getKey());
        $this->assertSame('red', $blue->getKey());
    }

    public function testMapModifyAttributeMessageRejectsDuplicateOptionKeys(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setType('options');

        $this->createOption($attribute, 1, 'red', 'Red');
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->expectException(AttributeOptionKeyNotUniqueException::class);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'attribute-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'options',
            'name' => 'Color',
            'options' => [
                ['id' => 1, 'type' => 'option', 'key' => 'red', 'name' => 'Red'],
                ['type' => 'option', 'key' => 'red', 'name' => 'Another red'],
            ],
        ]));
    }

    private function createOption(Attribute $attribute, int $id, string $key, string $name): AttributeOption
    {
        $option = new AttributeOption($attribute, $key);
        (new \ReflectionProperty(AttributeOption::class, 'id'))->setValue($option, $id);
        $option->addTranslation(new AttributeOptionTranslation($option, 'en', $name));
        $attribute->addOption($option);

        return $option;
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

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideFilterableByType(): iterable
    {
        yield 'number' => ['number', true];
        yield 'date' => ['date', true];
        yield 'options' => ['options', true];
        yield 'text' => ['text', false];
    }

    #[DataProvider('provideFilterableByType')]
    public function testMapAttributeDataFilterableOnlyForFilterableTypes(string $type, bool $expected): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'attr',
            'type' => $type,
            'name' => 'Attr',
            'filterable' => true,
            'group' => 'group-uuid',
        ]));

        $this->assertSame($expected, $attribute->isFilterable());
    }

    public function testMapModifyKeepsFilterableWhenNotSubmitted(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setType('number');
        $attribute->setFilterable(true);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'uuid-1'], [
            'locale' => 'en',
            'key' => 'attr',
            'type' => 'number',
            'name' => 'Attr',
        ]));

        $this->assertTrue($attribute->isFilterable());
    }

    public function testMapRejectsADateDisplayFormatThatRendersNoDate(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->expectException(InvalidDateDisplayFormatException::class);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'released',
            'type' => 'date',
            'name' => 'Released',
            'config' => ['displayFormat' => 'Released MMMM yyyy'],
            'group' => 'group-uuid',
        ]));
    }

    public function testMapModifyRejectsADateDisplayFormatThatRendersNoDate(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setType('date');

        $this->expectException(InvalidDateDisplayFormatException::class);

        $this->mapper->mapAttributeData($attribute, new ModifyAttributeMessage(['uuid' => 'uuid-1'], [
            'locale' => 'en',
            'key' => 'released',
            'type' => 'date',
            'name' => 'Released',
            'config' => ['displayFormat' => 'Released MMMM yyyy'],
        ]));
    }

    public function testMapAcceptsQuotedWordsInADateDisplayFormat(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'released',
            'type' => 'date',
            'name' => 'Released',
            'config' => ['displayFormat' => "'Released' MMMM yyyy"],
            'group' => 'group-uuid',
        ]));

        $this->assertSame(['displayFormat' => "'Released' MMMM yyyy"], $attribute->getConfig());
    }

    public function testMapLeavesTheDisplayFormatOfTextUnchecked(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);

        $this->mapper->mapAttributeData($attribute, new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'note',
            'type' => 'text',
            'name' => 'Note',
            'config' => ['displayFormat' => 'Released %value%'],
            'group' => 'group-uuid',
        ]));

        $this->assertSame(['displayFormat' => 'Released %value%'], $attribute->getConfig());
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
