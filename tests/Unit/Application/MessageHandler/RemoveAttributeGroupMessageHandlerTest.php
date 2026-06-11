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
use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;
use Sulu\Product\Application\MessageHandler\RemoveAttributeGroupMessageHandler;
use Sulu\Product\Domain\Exception\AttributeGroupNotEmptyException;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class RemoveAttributeGroupMessageHandlerTest extends TestCase
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

    public function testRemoveAttributeGroup(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($group);
        $this->attributeRepository->countBy(['group' => $group])
            ->willReturn(0);
        $this->attributeGroupRepository->remove($group)
            ->shouldBeCalledOnce();

        $handler = new RemoveAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
            $this->attributeRepository->reveal(),
        );

        ($handler)(new RemoveAttributeGroupMessage('group-uuid'));
    }

    public function testRemoveAttributeGroupThrowsWhenGroupHasAttributes(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeRepository->countBy(['group' => $group])
            ->willReturn(3);
        $this->attributeGroupRepository->remove($group)->shouldNotBeCalled();

        $handler = new RemoveAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
            $this->attributeRepository->reveal(),
        );

        $this->expectException(AttributeGroupNotEmptyException::class);

        ($handler)(new RemoveAttributeGroupMessage('group-uuid'));
    }

    public function testRemoveAttributeGroupThrowsNotFoundWhenMissing(): void
    {
        $this->attributeGroupRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = new RemoveAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
            $this->attributeRepository->reveal(),
        );

        $this->expectException(AttributeGroupNotFoundException::class);

        ($handler)(new RemoveAttributeGroupMessage('non-existent'));
    }
}
