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
use Sulu\Product\Application\Message\RemoveAttributeSetMessage;
use Sulu\Product\Application\MessageHandler\RemoveAttributeSetMessageHandler;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;

class RemoveAttributeSetMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeSetRepositoryInterface> */
    private ObjectProphecy $attributeSetRepository;

    protected function setUp(): void
    {
        $this->attributeSetRepository = $this->prophesize(AttributeSetRepositoryInterface::class);
    }

    public function testRemoveAttributeSet(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeSet);

        $this->attributeSetRepository->remove($attributeSet)
            ->shouldBeCalledOnce();

        $handler = new RemoveAttributeSetMessageHandler($this->attributeSetRepository->reveal());

        ($handler)(new RemoveAttributeSetMessage('set-uuid'));
    }

    public function testRemoveAttributeSetThrowsNotFoundWhenMissing(): void
    {
        $this->attributeSetRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = new RemoveAttributeSetMessageHandler($this->attributeSetRepository->reveal());

        $this->expectException(AttributeSetNotFoundException::class);

        ($handler)(new RemoveAttributeSetMessage('non-existent'));
    }
}
