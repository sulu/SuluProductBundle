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
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\MessageHandler\CreateAttributeMessageHandler;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class CreateAttributeMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
    }

    private function createHandler(): CreateAttributeMessageHandler
    {
        return new CreateAttributeMessageHandler(
            $this->attributeRepository->reveal(),
            [new AttributeMapper($this->attributeRepository->reveal())],
            $this->attributeGroupRepository->reveal(),
        );
    }

    private function makeGroup(string $uuid = 'group-uuid-1'): AttributeGroup
    {
        $group = new AttributeGroup();
        $group->setUuid($uuid);

        return $group;
    }

    public function testCreateAttribute(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();
        $this->attributeRepository->create($group)->shouldBeCalledOnce()->willReturn($attribute);
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $result = ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'description' => 'Color of the product',
            'group' => 'group-uuid-1',
        ]));

        $this->assertSame($attribute, $result);
        $this->assertSame('color', $attribute->getKey());
        $this->assertSame('text', $attribute->getType());

        $translation = $attribute->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('Color of the product', $translation->getDescription());
    }

    public function testCreateAttributeWithOptions(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();
        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $result = ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'size',
            'name' => 'Size',
            'type' => 'options',
            'description' => null,
            'group' => 'group-uuid-1',
            'options' => [
                ['type' => 'option', 'key' => 'small', 'name' => 'Small'],
                ['type' => 'option', 'key' => 'large', 'name' => 'Large'],
            ],
        ]));

        $options = $result->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame('small', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('large', $options[1]->getKey());
        $this->assertSame(1, $options[1]->getPosition());
        $this->assertSame('Small', $options[0]->getTranslation('en')?->getName());
        $this->assertSame('Large', $options[1]->getTranslation('en')?->getName());
    }

    public function testCreateAttributeWithConfig(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();
        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $result = ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'height',
            'name' => 'Height',
            'type' => 'number',
            'description' => null,
            'group' => 'group-uuid-1',
            'config' => [
                'unit' => 'CENTIMETER',
                'min' => 0.0,
                'max' => 100.0,
            ],
        ]));

        $this->assertSame([
            'unit' => 'CENTIMETER',
            'min' => 0.0,
            'max' => 100.0,
        ], $result->getConfig());
    }

    public function testCreateAttributeWithoutConfigLeavesEmptyArray(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();
        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $result = ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'description' => null,
            'group' => 'group-uuid-1',
        ]));

        $this->assertSame([], $result->getConfig());
    }

    public function testCreateAttributeSetsPositionFromData(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();
        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->findByGroupWithPositionAtLeast($group, 5)->willReturn([]);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'group' => 'group-uuid-1',
            'position' => 5,
        ]));

        $this->assertSame(5, $attribute->getPosition());
    }

    public function testCreateAttributeWithAttributeGroupCreatesJoinRecord(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(0);
        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'group' => 'group-uuid-1',
        ]));

        $this->assertSame($group, $attribute->getGroup());

        $groupAttributes = $group->getGroupAttributes();
        $this->assertCount(1, $groupAttributes);
        $this->assertSame($attribute, $groupAttributes[0]->getAttribute());
        $this->assertSame(0, $groupAttributes[0]->getPosition());
    }

    public function testCreateAttributeWithGroupAppendsPosition(): void
    {
        $group = $this->makeGroup();

        $existingAttr = new Attribute($group);
        $existingAttr->setKey('existing');
        $existingAttr->setType('text');
        $existingGroupAttr = new \Sulu\Product\Domain\Model\AttributeGroupAttribute($group, $existingAttr);
        $existingGroupAttr->setPosition(0);
        $group->addGroupAttribute($existingGroupAttr);

        $attribute2 = new Attribute($group);

        $this->attributeRepository->create($group)->willReturn($attribute2);
        $this->attributeRepository->save($attribute2)->shouldBeCalledOnce();
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(1);
        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'size',
            'name' => 'Size',
            'type' => 'text',
            'group' => 'group-uuid-1',
        ]));

        $groupAttributes = $group->getGroupAttributes();
        $this->assertCount(2, $groupAttributes);
        $this->assertSame(1, $groupAttributes[1]->getPosition());
    }

    public function testCreateAttributeAutoSetsPositionToMaxPlusOne(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findNextPositionInGroup($group)->willReturn(5);
        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'group' => 'group-uuid-1',
        ]));

        $this->assertSame(5, $attribute->getPosition());
    }

    public function testCreateAttributeWithExplicitPositionShiftsOthers(): void
    {
        $group = $this->makeGroup();
        $attribute = new Attribute($group);

        $displaced = new Attribute($group);
        $displaced->setPosition(2);

        $this->attributeRepository->create($group)->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();
        $this->attributeRepository->findByGroupWithPositionAtLeast($group, 2)->willReturn([$displaced]);
        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid-1'])->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        ($this->createHandler())(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'group' => 'group-uuid-1',
            'position' => 2,
        ]));

        $this->assertSame(2, $attribute->getPosition());
        $this->assertSame(3, $displaced->getPosition());
    }
}
