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
use Sulu\Product\Application\Message\ModifyAttributeSetMessage;
use Sulu\Product\Application\MessageHandler\ModifyAttributeSetMessageHandler;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Model\AttributeSetAttribute;
use Sulu\Product\Domain\Model\AttributeSetTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;

class ModifyAttributeSetMessageHandlerTest extends TestCase
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

    private function createHandler(): ModifyAttributeSetMessageHandler
    {
        return new ModifyAttributeSetMessageHandler(
            $this->attributeSetRepository->reveal(),
            $this->attributeRepository->reveal(),
        );
    }

    public function testModifyAttributeSetThrowsNotFoundWhenMissing(): void
    {
        $this->attributeSetRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = $this->createHandler();

        $this->expectException(AttributeSetNotFoundException::class);

        ($handler)(new ModifyAttributeSetMessage('non-existent', 'en', 'Name'));
    }

    public function testModifyAttributeSetCreatesTranslationWhenMissing(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        $result = ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'My Set', 'A description'));

        $this->assertSame($attributeSet, $result);

        $translation = $attributeSet->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('My Set', $translation->getName());
        $this->assertSame('A description', $translation->getDescription());
    }

    public function testModifyAttributeSetUpdatesExistingTranslation(): void
    {
        $attributeSet = new AttributeSet();
        $translation = new AttributeSetTranslation($attributeSet, 'en', 'Old Name');
        $translation->setDescription('Old description');
        $attributeSet->addTranslation($translation);

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'New Name', 'New description'));

        $this->assertSame('New Name', $translation->getName());
        $this->assertSame('New description', $translation->getDescription());
    }

    public function testModifyAttributeSetAddsNewSetAttribute(): void
    {
        $attributeSet = new AttributeSet();

        $attribute = new Attribute();
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-1'])
            ->willReturn($attribute);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'My Set', null, [
            ['attribute' => 'attr-uuid-1', 'required' => true],
        ]));

        $setAttributes = $attributeSet->getSetAttributes();
        $this->assertCount(1, $setAttributes);
        $this->assertSame($attribute, $setAttributes[0]->getAttribute());
        $this->assertTrue($setAttributes[0]->getRequired());
        $this->assertSame(0, $setAttributes[0]->getPosition());
    }

    public function testModifyAttributeSetUpdatesExistingSetAttribute(): void
    {
        $attributeSet = new AttributeSet();

        $attribute = new Attribute();
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $setAttr = new AttributeSetAttribute($attributeSet, $attribute);
        $setAttr->setRequired(false);
        $setAttr->setPosition(5);
        $attributeSet->addSetAttribute($setAttr);

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid-1'])->shouldNotBeCalled();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'My Set', null, [
            ['attribute' => 'attr-uuid-1', 'required' => true],
        ]));

        $setAttributes = $attributeSet->getSetAttributes();
        $this->assertCount(1, $setAttributes);
        $this->assertTrue($setAttributes[0]->getRequired());
        $this->assertSame(0, $setAttributes[0]->getPosition());
    }

    public function testModifyAttributeSetRemovesStaleSetAttributes(): void
    {
        $attributeSet = new AttributeSet();

        $attributeToKeep = new Attribute();
        $attributeToKeep->setUuid('attr-uuid-keep');
        $attributeToKeep->setKey('color');
        $attributeToKeep->setType('text');

        $attributeToRemove = new Attribute();
        $attributeToRemove->setUuid('attr-uuid-remove');
        $attributeToRemove->setKey('size');
        $attributeToRemove->setType('options');

        $setAttrKeep = new AttributeSetAttribute($attributeSet, $attributeToKeep);
        $setAttrKeep->setPosition(0);
        $setAttrRemove = new AttributeSetAttribute($attributeSet, $attributeToRemove);
        $setAttrRemove->setPosition(1);

        $attributeSet->addSetAttribute($setAttrKeep);
        $attributeSet->addSetAttribute($setAttrRemove);

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'My Set', null, [
            ['attribute' => 'attr-uuid-keep', 'required' => false],
        ]));

        $setAttributes = $attributeSet->getSetAttributes();
        $this->assertCount(1, $setAttributes);
        $this->assertSame($attributeToKeep, $setAttributes[0]->getAttribute());
        $this->assertSame(0, $setAttributes[0]->getPosition());
        $this->assertFalse($setAttributes[0]->getRequired());
    }

    public function testModifyAttributeSetSkipsMissingAttributeOnAdd(): void
    {
        $attributeSet = new AttributeSet();

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $this->attributeRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'My Set', null, [
            ['attribute' => 'non-existent', 'required' => false],
        ]));

        $this->assertCount(0, $attributeSet->getSetAttributes());
    }

    public function testModifyAttributeSetClearsAllSetAttributesWhenEmpty(): void
    {
        $attributeSet = new AttributeSet();

        $attribute = new Attribute();
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $setAttr = new AttributeSetAttribute($attributeSet, $attribute);
        $attributeSet->addSetAttribute($setAttr);

        $this->attributeSetRepository->findOneBy(['uuid' => 'set-uuid'])
            ->willReturn($attributeSet);
        $this->attributeSetRepository->save($attributeSet)->shouldBeCalledOnce();

        $handler = $this->createHandler();

        ($handler)(new ModifyAttributeSetMessage('set-uuid', 'en', 'My Set', null, []));

        $this->assertCount(0, $attributeSet->getSetAttributes());
    }
}
