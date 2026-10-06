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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;
use Sulu\Product\Application\MessageHandler\RemoveAttributeGroupMessageHandler;
use Sulu\Product\Domain\Event\AttributeGroupRemovedEvent;
use Sulu\Product\Domain\Exception\AttributeGroupNotEmptyException;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class RemoveAttributeGroupMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    protected function setUp(): void
    {
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
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
            $this->domainEventCollector->reveal(),
            $this->createStub(TrashManagerInterface::class),
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
            $this->domainEventCollector->reveal(),
            $this->createStub(TrashManagerInterface::class),
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
            $this->domainEventCollector->reveal(),
            $this->createStub(TrashManagerInterface::class),
        );

        $this->expectException(AttributeGroupNotFoundException::class);

        ($handler)(new RemoveAttributeGroupMessage('non-existent'));
    }

    public function testRemoveAttributeGroupStoresTrashItemAndCollectsEvent(): void
    {
        $group = new AttributeGroup();
        $group->setDefaultLocale('en');
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'Physical'));
        $this->attributeGroupRepository->findOneBy(['uuid' => 'group-uuid'])->willReturn($group);
        $this->attributeRepository->countBy(['group' => $group])->willReturn(0);

        $trashManager = $this->prophesize(TrashManagerInterface::class);
        $trashManager->store(AttributeGroupInterface::RESOURCE_KEY, $group)->shouldBeCalledOnce();
        $this->attributeGroupRepository->remove($group)->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::that(
            static fn (AttributeGroupRemovedEvent $event) => 'group-uuid' === $event->getResourceId()
                && 'Physical' === $event->getResourceTitle()
                && 'en' === $event->getResourceTitleLocale(),
        ))->shouldBeCalledOnce();

        $handler = new RemoveAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
            $this->attributeRepository->reveal(),
            $this->domainEventCollector->reveal(),
            $trashManager->reveal(),
        );

        ($handler)(new RemoveAttributeGroupMessage('group-uuid'));
    }
}
