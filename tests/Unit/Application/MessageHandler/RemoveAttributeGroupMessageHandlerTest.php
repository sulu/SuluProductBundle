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

class RemoveAttributeGroupMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
    }

    public function testRemoveAttributeGroup(): void
    {
        $attributeGroup = new AttributeGroup();

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeGroup);
        $this->attributeGroupRepository->countGroupAttributes(['attributeGroup' => $attributeGroup])
            ->willReturn(0);
        $this->attributeGroupRepository->remove($attributeGroup)
            ->shouldBeCalledOnce();

        $handler = new RemoveAttributeGroupMessageHandler($this->attributeGroupRepository->reveal());

        ($handler)(new RemoveAttributeGroupMessage('group-uuid'));
    }

    public function testRemoveAttributeGroupThrowsWhenGroupHasAttributes(): void
    {
        $attributeGroup = new AttributeGroup();

        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])
            ->willReturn($attributeGroup);
        $this->attributeGroupRepository->countGroupAttributes(['attributeGroup' => $attributeGroup])
            ->willReturn(3);
        $this->attributeGroupRepository->remove($attributeGroup)->shouldNotBeCalled();

        $handler = new RemoveAttributeGroupMessageHandler($this->attributeGroupRepository->reveal());

        $this->expectException(AttributeGroupNotEmptyException::class);

        ($handler)(new RemoveAttributeGroupMessage('group-uuid'));
    }

    public function testRemoveAttributeGroupThrowsNotFoundWhenMissing(): void
    {
        $this->attributeGroupRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = new RemoveAttributeGroupMessageHandler($this->attributeGroupRepository->reveal());

        $this->expectException(AttributeGroupNotFoundException::class);

        ($handler)(new RemoveAttributeGroupMessage('non-existent'));
    }
}
