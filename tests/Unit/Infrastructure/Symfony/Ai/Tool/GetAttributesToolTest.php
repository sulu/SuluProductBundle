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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Ai\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\GetAttributesTool;

#[CoversClass(GetAttributesTool::class)]
class GetAttributesToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    private GetAttributesTool $tool;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $this->tool = new GetAttributesTool($this->attributeRepository->reveal(), new MeasurementRegistry(null));
    }

    private function setId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity::class, 'id'))->setValue($entity, $id);
    }

    public function testInvokeReturnsTranslatedTextAttribute(): void
    {
        $group = new AttributeGroup();
        $this->setId($group, 1);
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'General'));

        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setPosition(0);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Color'));

        $this->attributeRepository->findBy()->willReturn([$attribute]);

        $result = ($this->tool)('en');

        $this->assertSame([[
            'key' => 'color',
            'name' => 'Color',
            'type' => AttributeInterface::TYPE_TEXT,
            'group' => 'General',
            'unit' => null,
            'options' => [],
        ]], $result);
    }

    public function testInvokeFallsBackToKeyWhenTranslationMissing(): void
    {
        $group = new AttributeGroup();
        $this->setId($group, 1);

        $attribute = new Attribute($group);
        $attribute->setKey('size');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setPosition(0);

        $this->attributeRepository->findBy()->willReturn([$attribute]);

        $result = ($this->tool)('en');

        $this->assertSame('size', $result[0]['name']);
        $this->assertSame('', $result[0]['group']);
    }

    public function testInvokeResolvesUnitSymbolFromConfig(): void
    {
        $group = new AttributeGroup();
        $this->setId($group, 1);

        $attribute = new Attribute($group);
        $attribute->setKey('level');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setPosition(0);
        $attribute->setConfig(['unit' => 'DECIBEL']);

        $this->attributeRepository->findBy()->willReturn([$attribute]);

        $result = ($this->tool)('en');

        $this->assertSame('dB', $result[0]['unit']);
    }

    public function testInvokeReturnsOptionsWithTranslatedLabels(): void
    {
        $group = new AttributeGroup();
        $this->setId($group, 1);

        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $attribute->setPosition(0);

        $translatedOption = new AttributeOption($attribute, 'red');
        $translatedOption->addTranslation(new AttributeOptionTranslation($translatedOption, 'en', 'Red'));
        $attribute->addOption($translatedOption);

        $untranslatedOption = new AttributeOption($attribute, 'blue');
        $attribute->addOption($untranslatedOption);

        $this->attributeRepository->findBy()->willReturn([$attribute]);

        $result = ($this->tool)('en');

        $this->assertSame([
            ['key' => 'red', 'label' => 'Red'],
            ['key' => 'blue', 'label' => 'blue'],
        ], $result[0]['options']);
    }

    public function testInvokeOrdersByGroupThenPosition(): void
    {
        $groupA = new AttributeGroup();
        $this->setId($groupA, 2);
        $groupA->addTranslation(new AttributeGroupTranslation($groupA, 'en', 'B Group'));

        $groupB = new AttributeGroup();
        $this->setId($groupB, 1);
        $groupB->addTranslation(new AttributeGroupTranslation($groupB, 'en', 'A Group'));

        $second = new Attribute($groupA);
        $second->setKey('second');
        $second->setType(AttributeInterface::TYPE_TEXT);
        $second->setPosition(0);

        $first = new Attribute($groupB);
        $first->setKey('first');
        $first->setType(AttributeInterface::TYPE_TEXT);
        $first->setPosition(0);

        $this->attributeRepository->findBy()->willReturn([$second, $first]);

        $result = ($this->tool)('en');

        $this->assertSame(['first', 'second'], \array_column($result, 'key'));
    }

    public function testInvokeFiltersByGroupSubstringCaseInsensitive(): void
    {
        $matching = new AttributeGroup();
        $this->setId($matching, 1);
        $matching->addTranslation(new AttributeGroupTranslation($matching, 'en', 'Electrical Specs'));

        $other = new AttributeGroup();
        $this->setId($other, 2);
        $other->addTranslation(new AttributeGroupTranslation($other, 'en', 'Mechanical'));

        $matchingAttribute = new Attribute($matching);
        $matchingAttribute->setKey('current');
        $matchingAttribute->setType(AttributeInterface::TYPE_NUMBER);
        $matchingAttribute->setPosition(0);

        $otherAttribute = new Attribute($other);
        $otherAttribute->setKey('weight');
        $otherAttribute->setType(AttributeInterface::TYPE_NUMBER);
        $otherAttribute->setPosition(0);

        $this->attributeRepository->findBy()->willReturn([$matchingAttribute, $otherAttribute]);

        $result = ($this->tool)('en', 'electrical');

        $this->assertSame(['current'], \array_column($result, 'key'));
    }

    public function testInvokeReturnsEmptyArrayWhenNoAttributes(): void
    {
        $this->attributeRepository->findBy()->willReturn([]);

        $this->assertSame([], ($this->tool)('en'));
    }
}
