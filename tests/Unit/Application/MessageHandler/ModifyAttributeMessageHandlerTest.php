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

namespace Sulu\Product\Tests\Unit\Application\MessageHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mapper\AttributeMapper;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Application\MessageHandler\ModifyAttributeMessageHandler;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class ModifyAttributeMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    private function createHandler(): ModifyAttributeMessageHandler
    {
        return new ModifyAttributeMessageHandler(
            $this->attributeRepository->reveal(),
            [new AttributeMapper($this->attributeRepository->reveal())],
        );
    }

    public function testModifyAttributeThrowsNotFoundWhenMissing(): void
    {
        $this->attributeRepository->getOneBy(['uuid' => 'non-existent'])
            ->willThrow(new AttributeNotFoundException(['uuid' => 'non-existent']));

        $handler = $this->createHandler();

        $this->expectException(AttributeNotFoundException::class);

        ($handler)(new ModifyAttributeMessage(['uuid' => 'non-existent'], ['locale' => 'en', 'key' => 'color', 'type' => 'text', 'name' => 'Color']));
    }

    public function testModifyAttributeUpdatesKeyButNotType(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('original-key');
        $attribute->setType('text');
        $attribute->setPosition(0);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'new-key',
            'type' => 'options',
            'name' => 'Name',
            'position' => 0,
        ]));

        $this->assertSame('new-key', $attribute->getKey());
        $this->assertSame('text', $attribute->getType());
    }

    public function testModifyAttributeCreatesTranslationWhenMissing(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'description' => 'A color attribute',
            'position' => 0,
        ]));

        $translation = $attribute->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('A color attribute', $translation->getDescription());
    }

    public function testModifyAttributeUpdatesExistingTranslation(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);
        $translation = new AttributeTranslation($attribute, 'en', 'Old Name');
        $translation->setDescription('Old description');
        $attribute->addTranslation($translation);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'New Name',
            'description' => 'New description',
            'position' => 0,
        ]));

        $this->assertSame('New Name', $translation->getName());
        $this->assertSame('New description', $translation->getDescription());
    }

    public function testModifyAttributeAddsNewOption(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'size',
            'type' => 'options',
            'name' => 'Size',
            'position' => 0,
            'options' => [
                ['type' => 'option', 'key' => 'small', 'name' => 'Small'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame('small', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('Small', $options[0]->getTranslation('en')?->getName());
    }

    public function testModifyAttributeUpdatesExistingOptionTranslation(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);
        $option = new AttributeOption($attribute, 'small');
        $optionTranslation = new AttributeOptionTranslation($option, 'en', 'Small');
        $option->addTranslation($optionTranslation);
        $attribute->addOption($option);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'size',
            'type' => 'options',
            'name' => 'Size',
            'position' => 0,
            'options' => [
                ['type' => 'option', 'key' => 'small', 'name' => 'Petit'],
            ],
        ]));

        $this->assertSame('Petit', $optionTranslation->getName());
    }

    public function testModifyAttributeRemovesStaleOptions(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);
        $optionToKeep = new AttributeOption($attribute, 'large');
        $optionToRemove = new AttributeOption($attribute, 'small');
        $attribute->addOption($optionToKeep);
        $attribute->addOption($optionToRemove);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'size',
            'type' => 'options',
            'name' => 'Size',
            'position' => 0,
            'options' => [
                ['type' => 'option', 'key' => 'large', 'name' => 'Large'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame('large', \reset($options)->getKey());
    }

    public function testModifyAttributeSetsConfig(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'weight',
            'type' => 'number',
            'name' => 'Weight',
            'position' => 0,
            'config' => [
                'unit' => 'KILOGRAM',
                'min' => 1.0,
                'max' => 50.0,
            ],
        ]));

        $this->assertSame([
            'unit' => 'KILOGRAM',
            'min' => 1.0,
            'max' => 50.0,
        ], $attribute->getConfig());
    }

    public function testModifyAttributeClearsConfigWhenEmptyArrayPassed(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setPosition(0);
        $attribute->setConfig(['measurementFamily' => 'weight', 'unit' => 'KILOGRAM']);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'weight',
            'type' => 'number',
            'name' => 'Weight',
            'position' => 0,
            'config' => [],
        ]));

        $this->assertSame([], $attribute->getConfig());
    }

    public function testModifyAttributeUpdatesPositionFromData(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setPosition(0);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findByGroupWithPositionBetween($group, 1, 3, $attribute)->willReturn([]);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'position' => 3,
        ]));

        $this->assertSame(3, $attribute->getPosition());
    }

    public function testModifyAttributePositionChangeShiftsOthersInGroup(): void
    {
        $group = new AttributeGroup();
        $group->setUuid('group-uuid-1');
        $attribute = new Attribute($group);
        $attribute->setPosition(5);

        $displaced = new Attribute(new AttributeGroup());
        $displaced->setPosition(2);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findByGroupWithPositionAtLeast($group, 2, $attribute)->willReturn([$displaced]);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'position' => 2,
        ]));

        $this->assertSame(2, $attribute->getPosition());
        $this->assertSame(3, $displaced->getPosition());
    }

    public function testModifyAttributeNullPositionMovesToEndAndShiftsGap(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setPosition(1);

        $other1 = new Attribute(new AttributeGroup());
        $other1->setPosition(2);
        $other2 = new Attribute(new AttributeGroup());
        $other2->setPosition(3);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(4);
        $this->attributeRepository->findByGroupWithPositionBetween($group, 2, 3, $attribute)->willReturn([$other1, $other2]);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'position' => null,
        ]));

        $this->assertSame(3, $attribute->getPosition());
        $this->assertSame(1, $other1->getPosition());
        $this->assertSame(2, $other2->getPosition());
    }

    public function testModifyAttributeMoveUpShiftsOthersDown(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setPosition(1);

        $between = new Attribute(new AttributeGroup());
        $between->setPosition(2);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findByGroupWithPositionBetween($group, 2, 3, $attribute)->willReturn([$between]);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'position' => 3,
        ]));

        $this->assertSame(3, $attribute->getPosition());
        $this->assertSame(1, $between->getPosition());
    }

    public function testModifyAttributeSamePositionDoesNotShift(): void
    {
        $group = new AttributeGroup();
        $group->setUuid('group-uuid-1');
        $attribute = new Attribute($group);
        $attribute->setPosition(3);

        $this->attributeRepository->getOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findByGroupWithPositionAtLeast($group, 3, $attribute)->shouldNotBeCalled();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'color',
            'type' => 'text',
            'name' => 'Color',
            'position' => 3,
        ]));

        $this->assertSame(3, $attribute->getPosition());
    }
}
