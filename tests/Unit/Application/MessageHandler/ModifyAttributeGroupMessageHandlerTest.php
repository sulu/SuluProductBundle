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
use Sulu\Product\Application\Message\ModifyAttributeGroupMessage;
use Sulu\Product\Application\MessageHandler\ModifyAttributeGroupMessageHandler;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class ModifyAttributeGroupMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    private function createHandler(): ModifyAttributeGroupMessageHandler
    {
        return new ModifyAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
            $this->attributeRepository->reveal(),
        );
    }

    public function testModifyAttributeGroupThrowsNotFoundWhenMissing(): void
    {
        $this->attributeGroupRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = $this->createHandler();

        $this->expectException(AttributeGroupNotFoundException::class);

        ($handler)(new ModifyAttributeGroupMessage('non-existent', 'en', 'Name'));
    }

    public function testModifyAttributeGroupCreatesTranslationWhenMissing(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $result = ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'My Group', 'A description'));

        $this->assertSame($group, $result);

        $translation = $group->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('My Group', $translation->getName());
        $this->assertSame('A description', $translation->getDescription());
    }

    public function testModifyAttributeGroupUpdatesExistingTranslation(): void
    {
        $group = new AttributeGroup();
        $translation = new AttributeGroupTranslation($group, 'en', 'Old Name');
        $translation->setDescription('Old description');
        $group->addTranslation($translation);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'New Name', 'New description'));

        $this->assertSame('New Name', $translation->getName());
        $this->assertSame('New description', $translation->getDescription());
    }

    public function testModifyAttributeGroupAddsNewGroupAttribute(): void
    {
        $group = new AttributeGroup();

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-1'])
            ->willReturn($attribute);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'My Group', null, [
            ['attribute' => 'attr-uuid-1'],
        ]));

        $groupAttributes = $group->getGroupAttributes();
        $this->assertCount(1, $groupAttributes);
        $this->assertSame($attribute, $groupAttributes[0]->getAttribute());
        $this->assertSame(0, $groupAttributes[0]->getPosition());
    }

    public function testModifyAttributeGroupUpdatesExistingGroupAttribute(): void
    {
        $group = new AttributeGroup();

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $groupAttr = new AttributeGroupAttribute($group, $attribute);
        $groupAttr->setPosition(5);
        $group->addGroupAttribute($groupAttr);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-1'])->shouldNotBeCalled();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'My Group', null, [
            ['attribute' => 'attr-uuid-1'],
        ]));

        $groupAttributes = $group->getGroupAttributes();
        $this->assertCount(1, $groupAttributes);
        $this->assertSame(0, $groupAttributes[0]->getPosition());
    }

    public function testModifyAttributeGroupRemovesStaleGroupAttributes(): void
    {
        $group = new AttributeGroup();

        $attributeToKeep = new Attribute(new AttributeGroup());
        $attributeToKeep->setUuid('attr-uuid-keep');
        $attributeToKeep->setKey('color');
        $attributeToKeep->setType('text');

        $attributeToRemove = new Attribute(new AttributeGroup());
        $attributeToRemove->setUuid('attr-uuid-remove');
        $attributeToRemove->setKey('size');
        $attributeToRemove->setType('options');

        $groupAttrKeep = new AttributeGroupAttribute($group, $attributeToKeep);
        $groupAttrKeep->setPosition(0);
        $groupAttrRemove = new AttributeGroupAttribute($group, $attributeToRemove);
        $groupAttrRemove->setPosition(1);

        $group->addGroupAttribute($groupAttrKeep);
        $group->addGroupAttribute($groupAttrRemove);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'My Group', null, [
            ['attribute' => 'attr-uuid-keep'],
        ]));

        $groupAttributes = $group->getGroupAttributes();
        $this->assertCount(1, $groupAttributes);
        $this->assertSame($attributeToKeep, $groupAttributes[0]->getAttribute());
        $this->assertSame(0, $groupAttributes[0]->getPosition());
    }

    public function testModifyAttributeGroupSkipsMissingAttributeOnAdd(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'My Group', null, [
            ['attribute' => 'non-existent'],
        ]));

        $this->assertCount(0, $group->getGroupAttributes());
    }

    public function testModifyAttributeGroupClearsAllGroupAttributesWhenEmpty(): void
    {
        $group = new AttributeGroup();

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $groupAttr = new AttributeGroupAttribute($group, $attribute);
        $group->addGroupAttribute($groupAttr);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeGroupMessage('group-uuid', 'en', 'My Group', null, []));

        $this->assertCount(0, $group->getGroupAttributes());
    }
}
