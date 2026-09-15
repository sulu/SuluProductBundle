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
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;

class ModifyAttributeGroupMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
    }

    private function createHandler(): ModifyAttributeGroupMessageHandler
    {
        return new ModifyAttributeGroupMessageHandler(
            $this->attributeGroupRepository->reveal(),
        );
    }

    private function makeMessage(string $uuid, string $locale, string $name, ?string $description = null): ModifyAttributeGroupMessage
    {
        $data = ['locale' => $locale, 'name' => $name];
        if (null !== $description) {
            $data['description'] = $description;
        }

        return new ModifyAttributeGroupMessage(['uuid' => $uuid], $data);
    }

    public function testModifyAttributeGroupThrowsNotFoundWhenMissing(): void
    {
        $this->attributeGroupRepository->getOneBy(['uuid' => 'non-existent'])
            ->willThrow(new AttributeGroupNotFoundException(['uuid' => 'non-existent']));

        $handler = $this->createHandler();

        $this->expectException(AttributeGroupNotFoundException::class);

        ($handler)($this->makeMessage('non-existent', 'en', 'Name'));
    }

    public function testModifyAttributeGroupCreatesTranslationWhenMissing(): void
    {
        $group = new AttributeGroup();

        $this->attributeGroupRepository->getOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $result = ($handler)($this->makeMessage('group-uuid', 'en', 'My Group', 'A description'));

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

        $this->attributeGroupRepository->getOneBy(['uuid' => 'group-uuid'])
            ->willReturn($group);
        $this->attributeGroupRepository->save($group)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)($this->makeMessage('group-uuid', 'en', 'New Name', 'New description'));

        $this->assertSame('New Name', $translation->getName());
        $this->assertSame('New description', $translation->getDescription());
    }
}
