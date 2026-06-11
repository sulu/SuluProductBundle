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
use Sulu\Product\Application\Message\CreateAttributeGroupMessage;
use Sulu\Product\Application\MessageHandler\CreateAttributeGroupMessageHandler;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class CreateAttributeGroupMessageHandlerTest extends TestCase
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

    private function createHandler(): CreateAttributeGroupMessageHandler
    {
        return new CreateAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
            $this->attributeRepository->reveal(),
        );
    }

    public function testCreateAttributeGroup(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->create()
            ->shouldBeCalledOnce()
            ->willReturn($group);

        $this->attributeGroupRepository->save($group)
            ->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage('en', 'My Group', 'A description');

        $result = ($handler)($message);

        $this->assertSame($group, $result);

        $translation = $group->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('My Group', $translation->getName());
        $this->assertSame('A description', $translation->getDescription());
    }

    public function testCreateAttributeGroupWithNullDescription(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->create()->shouldBeCalledOnce()->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage('en', 'My Group', null);

        ($handler)($message);

        $translation = $group->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertNull($translation->getDescription());
    }

    public function testCreateAttributeGroupWithEmptyAttributes(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->create()->shouldBeCalledOnce()->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage('en', 'My Group', null, []);

        ($handler)($message);

        $this->assertCount(0, $group->getGroupAttributes());
    }

    public function testCreateAttributeGroupWithAttributes(): void
    {
        $group = new AttributeGroup();

        $attribute1 = new Attribute(new AttributeGroup());
        $attribute1->setUuid('attr-uuid-1');
        $attribute1->setKey('color');
        $attribute1->setType('text');

        $attribute2 = new Attribute(new AttributeGroup());
        $attribute2->setUuid('attr-uuid-2');
        $attribute2->setKey('size');
        $attribute2->setType('options');

        $this->attributeGroupRepository->create()->shouldBeCalledOnce()->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-1'])
            ->willReturn($attribute1);
        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-2'])
            ->willReturn($attribute2);

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage('en', 'My Group', null, [
            ['attribute' => 'attr-uuid-1'],
            ['attribute' => 'attr-uuid-2'],
        ]);

        ($handler)($message);

        $groupAttributes = $group->getGroupAttributes();
        $this->assertCount(2, $groupAttributes);
        $this->assertSame($attribute1, $groupAttributes[0]->getAttribute());
        $this->assertSame(0, $groupAttributes[0]->getPosition());
        $this->assertSame($attribute2, $groupAttributes[1]->getAttribute());
        $this->assertSame(1, $groupAttributes[1]->getPosition());
    }

    public function testCreateAttributeGroupSkipsMissingAttribute(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->create()->shouldBeCalledOnce()->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage('en', 'My Group', null, [
            ['attribute' => 'non-existent'],
        ]);

        ($handler)($message);

        $this->assertCount(0, $group->getGroupAttributes());
    }
}
