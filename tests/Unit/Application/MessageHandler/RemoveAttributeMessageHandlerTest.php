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
use Sulu\Product\Application\Message\RemoveAttributeMessage;
use Sulu\Product\Application\MessageHandler\RemoveAttributeMessageHandler;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class RemoveAttributeMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    public function testRemoveAttribute(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($attribute);

        $this->attributeRepository->remove($attribute)
            ->shouldBeCalledOnce();

        $handler = new RemoveAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new RemoveAttributeMessage(['uuid' => 'attr-uuid']));
    }

    public function testRemoveAttributeThrowsNotFoundWhenMissing(): void
    {
        $this->attributeRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = new RemoveAttributeMessageHandler($this->attributeRepository->reveal());

        $this->expectException(AttributeNotFoundException::class);

        ($handler)(new RemoveAttributeMessage(['uuid' => 'non-existent']));
    }
}
