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
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Application\MessageHandler\ModifyAttributeMessageHandler;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class ModifyAttributeMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    public function testModifyAttributeThrowsNotFoundWhenMissing(): void
    {
        $this->attributeRepository->findOneBy(['uuid' => 'non-existent'])
            ->willReturn(null);

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        $this->expectException(AttributeNotFoundException::class);

        ($handler)(new ModifyAttributeMessage(['uuid' => 'non-existent'], ['locale' => 'en']));
    }

    public function testModifyAttributeUpdatesKeyAndType(): void
    {
        $attribute = new Attribute();
        $attribute->setKey('old-key');
        $attribute->setType('text');

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        $result = ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'key' => 'new-key',
            'type' => 'options',
            'name' => 'Name',
        ]));

        $this->assertSame($attribute, $result);
        $this->assertSame('new-key', $attribute->getKey());
        $this->assertSame('options', $attribute->getType());
    }

    public function testModifyAttributeCreatesTranslationWhenMissing(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'name' => 'Color',
            'description' => 'A color attribute',
        ]));

        $translation = $attribute->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('A color attribute', $translation->getDescription());
    }

    public function testModifyAttributeUpdatesExistingTranslation(): void
    {
        $attribute = new Attribute();
        $translation = new AttributeTranslation($attribute, 'en', 'Old Name');
        $translation->setDescription('Old description');
        $attribute->addTranslation($translation);

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'name' => 'New Name',
            'description' => 'New description',
        ]));

        $this->assertSame('New Name', $translation->getName());
        $this->assertSame('New description', $translation->getDescription());
    }

    public function testModifyAttributeAddsNewOption(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'name' => 'Size',
            'options' => [
                ['type' => 'option', 'key' => 'small', 'name' => 'Small'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame('small', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('Small', $options[0]->getTranslation('en')?->getName());
    }

    public function testModifyAttributeUpdatesExistingOptionTranslation(): void
    {
        $attribute = new Attribute();
        $option = new AttributeOption($attribute, 'small');
        $optionTranslation = new AttributeOptionTranslation($option, 'en', 'Small');
        $option->addTranslation($optionTranslation);
        $attribute->addOption($option);

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'name' => 'Size',
            'options' => [
                ['type' => 'option', 'key' => 'small', 'name' => 'Petit'],
            ],
        ]));

        $this->assertSame('Petit', $optionTranslation->getName());
    }

    public function testModifyAttributeRemovesStaleOptions(): void
    {
        $attribute = new Attribute();
        $optionToKeep = new AttributeOption($attribute, 'large');
        $optionToRemove = new AttributeOption($attribute, 'small');
        $attribute->addOption($optionToKeep);
        $attribute->addOption($optionToRemove);

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'name' => 'Size',
            'options' => [
                ['type' => 'option', 'key' => 'large', 'name' => 'Large'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame('large', \reset($options)->getKey());
    }

    public function testModifyAttributeSkipsEmptyOptionKey(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->findOneBy(['uuid' => 'attr-uuid'])->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new ModifyAttributeMessageHandler($this->attributeRepository->reveal());

        ($handler)(new ModifyAttributeMessage(['uuid' => 'attr-uuid'], [
            'locale' => 'en',
            'name' => 'Size',
            'options' => [
                ['type' => 'option', 'key' => '', 'name' => 'Empty'],
                ['type' => 'option', 'key' => 'large', 'name' => 'Large'],
            ],
        ]));

        $options = $attribute->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame('large', $options[0]->getKey());
    }
}
