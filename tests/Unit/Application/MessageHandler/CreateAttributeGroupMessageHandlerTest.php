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
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;

class CreateAttributeGroupMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
    }

    private function createHandler(): CreateAttributeGroupMessageHandler
    {
        return new CreateAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
        );
    }

    public function testCreateAttributeGroup(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->createNew()
            ->shouldBeCalledOnce()
            ->willReturn($group);

        $this->attributeGroupRepository->save($group)
            ->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage([
            'locale' => 'en',
            'name' => 'My Group',
            'description' => 'A description',
        ]);

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

        $this->attributeGroupRepository->createNew()->shouldBeCalledOnce()->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeGroupMessage([
            'locale' => 'en',
            'name' => 'My Group',
        ]);

        ($handler)($message);

        $translation = $group->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertNull($translation->getDescription());
    }
}
