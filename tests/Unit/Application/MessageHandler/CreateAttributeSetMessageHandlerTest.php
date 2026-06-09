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
use Sulu\Product\Application\Message\CreateAttributeSetMessage;
use Sulu\Product\Application\MessageHandler\CreateAttributeSetMessageHandler;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;

class CreateAttributeSetMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeSetRepositoryInterface> */
    private ObjectProphecy $attributeSetRepository;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeSetRepository = $this->prophesize(AttributeSetRepositoryInterface::class);
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    private function createHandler(): CreateAttributeSetMessageHandler
    {
        return new CreateAttributeSetMessageHandler(
            $this->attributeSetRepository->reveal(),
            $this->attributeRepository->reveal(),
        );
    }

    public function testCreateAttributeSet(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->create()
            ->shouldBeCalledOnce()
            ->willReturn($attributeSet);

        $this->attributeSetRepository->save($attributeSet)
            ->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeSetMessage('en', 'My Set', 'A description');

        $result = ($handler)($message);

        $this->assertSame($attributeSet, $result);

        $translation = $attributeSet->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('My Set', $translation->getName());
        $this->assertSame('A description', $translation->getDescription());
    }

    public function testCreateAttributeSetWithNullDescription(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->create()->shouldBeCalledOnce()->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeSetMessage('en', 'My Set', null);

        ($handler)($message);

        $translation = $attributeSet->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertNull($translation->getDescription());
    }

    public function testCreateAttributeSetWithEmptyAttributes(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->create()->shouldBeCalledOnce()->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $message = new CreateAttributeSetMessage('en', 'My Set', null, []);

        ($handler)($message);

        $this->assertCount(0, $attributeSet->getSetAttributes());
    }

    public function testCreateAttributeSetWithAttributes(): void
    {
        $attributeSet = new AttributeSet();

        $attribute1 = new Attribute();
        $attribute1->setUuid('attr-uuid-1');
        $attribute1->setKey('color');
        $attribute1->setType('text');

        $attribute2 = new Attribute();
        $attribute2->setUuid('attr-uuid-2');
        $attribute2->setKey('size');
        $attribute2->setType('options');

        $this->attributeSetRepository->create()->shouldBeCalledOnce()->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-1'])
            ->willReturn($attribute1);
        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-2'])
            ->willReturn($attribute2);

        $handler = $this->createHandler();

        $message = new CreateAttributeSetMessage('en', 'My Set', null, [
            ['attribute' => 'attr-uuid-1', 'required' => true],
            ['attribute' => 'attr-uuid-2', 'required' => false],
        ]);

        ($handler)($message);

        $setAttributes = $attributeSet->getSetAttributes();
        $this->assertCount(2, $setAttributes);
        $this->assertSame($attribute1, $setAttributes[0]->getAttribute());
        $this->assertTrue($setAttributes[0]->getRequired());
        $this->assertSame(0, $setAttributes[0]->getPosition());
        $this->assertSame($attribute2, $setAttributes[1]->getAttribute());
        $this->assertFalse($setAttributes[1]->getRequired());
        $this->assertSame(1, $setAttributes[1]->getPosition());
    }

    public function testCreateAttributeSetSkipsMissingAttribute(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->create()->shouldBeCalledOnce()->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = $this->createHandler();

        $message = new CreateAttributeSetMessage('en', 'My Set', null, [
            ['attribute' => 'non-existent', 'required' => false],
        ]);

        ($handler)($message);

        $this->assertCount(0, $attributeSet->getSetAttributes());
    }
}
