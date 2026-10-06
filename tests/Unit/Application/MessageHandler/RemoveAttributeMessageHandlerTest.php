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
use Sulu\Product\Application\Message\RemoveAttributeMessage;
use Sulu\Product\Application\MessageHandler\RemoveAttributeMessageHandler;
use Sulu\Product\Domain\Event\AttributeRemovedEvent;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class RemoveAttributeMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    protected function setUp(): void
    {
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    public function testRemoveAttribute(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $identifier = [
            'key' => 'color',
        ];

        $this->attributeRepository->getOneBy($identifier)
            ->shouldBeCalledOnce()
            ->willReturn($attribute);

        $this->attributeRepository->findOneBy($identifier)
            ->shouldNotBeCalled();

        $this->attributeRepository->remove($attribute)
            ->shouldBeCalledOnce();

        $handler = new RemoveAttributeMessageHandler(
            $this->attributeRepository->reveal(),
            $this->domainEventCollector->reveal(),
            $this->createStub(TrashManagerInterface::class),
        );

        ($handler)(new RemoveAttributeMessage($identifier));
    }

    public function testRemoveAttributeThrowsNotFoundWhenMissing(): void
    {
        $identifier = ['key' => 'non-existent'];

        $this->attributeRepository->getOneBy($identifier)
            ->willThrow(new AttributeNotFoundException($identifier));

        $handler = new RemoveAttributeMessageHandler(
            $this->attributeRepository->reveal(),
            $this->domainEventCollector->reveal(),
            $this->createStub(TrashManagerInterface::class),
        );

        $this->expectException(AttributeNotFoundException::class);

        ($handler)(new RemoveAttributeMessage($identifier));
    }

    public function testRemoveAttributeStoresTrashItemAndCollectsEvent(): void
    {
        $attribute = new Attribute(new AttributeGroup(), 'attribute-uuid');
        $attribute->setDefaultLocale('en');
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Color'));
        $this->attributeRepository->getOneBy(['uuid' => 'attribute-uuid'])->willReturn($attribute);

        $trashManager = $this->prophesize(TrashManagerInterface::class);
        $trashManager->store(AttributeInterface::RESOURCE_KEY, $attribute)->shouldBeCalledOnce();
        $this->attributeRepository->remove($attribute)->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::that(
            static fn (AttributeRemovedEvent $event) => 'attribute-uuid' === $event->getResourceId()
                && 'Color' === $event->getResourceTitle()
                && 'en' === $event->getResourceTitleLocale(),
        ))->shouldBeCalledOnce();

        $handler = new RemoveAttributeMessageHandler(
            $this->attributeRepository->reveal(),
            $this->domainEventCollector->reveal(),
            $trashManager->reveal(),
        );

        ($handler)(new RemoveAttributeMessage(['uuid' => 'attribute-uuid']));
    }
}
