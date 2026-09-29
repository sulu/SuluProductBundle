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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\AttributeListTool;

#[CoversClass(AttributeListTool::class)]
final class AttributeListToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;
    private AttributeListTool $tool;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
        $this->tool = new AttributeListTool($this->attributeGroupRepository->reveal());
    }

    public function testListAttributesExposesTheIntegerIdUsedAsAttributeKey(): void
    {
        $group = new AttributeGroup();
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'Appearance'));

        $this->addAttribute($group, 12, 'colour', AttributeInterface::TYPE_TEXT, 'Colour');

        // Pinned rather than matched loosely: the listing reads a translation per group,
        // then its attributes, then a translation per attribute. Losing a select here
        // costs a query per row and nothing else would notice.
        $this->attributeGroupRepository->findBy([], [], [
            AttributeGroupRepositoryInterface::SELECT_GROUP_TRANSLATIONS => true,
            AttributeGroupRepositoryInterface::SELECT_GROUP_ATTRIBUTES => true,
            AttributeGroupRepositoryInterface::SELECT_GROUP_ATTRIBUTE_TRANSLATIONS => true,
        ])->willReturn([$group])->shouldBeCalledOnce();

        $result = $this->tool->listAttributes('en');

        $this->assertSame(1, $result['total']);

        $this->assertIsArray($result['attributes']);
        $attribute = $result['attributes'][0];
        $this->assertIsArray($attribute);
        $this->assertSame('Appearance', $attribute['group']);
        $this->assertSame(12, $attribute['id']);
        $this->assertSame('colour', $attribute['key']);
        $this->assertSame(AttributeInterface::TYPE_TEXT, $attribute['type']);
        $this->assertSame('Colour', $attribute['name']);
        $this->assertArrayNotHasKey('options', $attribute);
    }

    public function testListAttributesIncludesOptionsForOptionAttributes(): void
    {
        $group = new AttributeGroup();
        $attribute = $this->addAttribute($group, 13, 'size', AttributeInterface::TYPE_OPTIONS, 'Size');

        $option = new AttributeOption($attribute, 'xl');
        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Extra Large'));
        $attribute->addOption($option);

        $this->attributeGroupRepository->findBy(Argument::cetera())->willReturn([$group]);

        $result = $this->tool->listAttributes('en');

        $this->assertIsArray($result['attributes']);
        $attribute = $result['attributes'][0];
        $this->assertIsArray($attribute);
        $this->assertSame([['key' => 'xl', 'name' => 'Extra Large']], $attribute['options']);
    }

    public function testListAttributesFallsBackToTheKeyWhenALocaleIsMissing(): void
    {
        $group = new AttributeGroup();
        $this->addAttribute($group, 14, 'material', AttributeInterface::TYPE_TEXT, 'Material');

        $this->attributeGroupRepository->findBy(Argument::cetera())->willReturn([$group]);

        $result = $this->tool->listAttributes('de');

        $this->assertIsArray($result['attributes']);
        $attribute = $result['attributes'][0];
        $this->assertIsArray($attribute);
        $this->assertSame('material', $attribute['name']);
    }

    public function testListAttributesSortsAndPagesTheFlattenedList(): void
    {
        $group = new AttributeGroup();
        $this->addAttribute($group, 30, 'alpha', AttributeInterface::TYPE_TEXT, 'Alpha');
        $this->addAttribute($group, 31, 'bravo', AttributeInterface::TYPE_TEXT, 'Bravo');
        $this->addAttribute($group, 32, 'charlie', AttributeInterface::TYPE_TEXT, 'Charlie');

        $this->attributeGroupRepository->findBy(Argument::cetera())->willReturn([$group]);

        $descending = $this->tool->listAttributes('en', sortBy: 'key', sortOrder: 'desc');
        $this->assertIsArray($descending['attributes']);
        $this->assertSame(
            ['charlie', 'bravo', 'alpha'],
            \array_column($descending['attributes'], 'key'),
            'A sort field the tool advertises must actually reorder the result.',
        );

        $secondPage = $this->tool->listAttributes('en', page: 2, limit: 2);
        $this->assertSame(3, $secondPage['total']);
        $this->assertIsArray($secondPage['attributes']);
        $this->assertSame(['charlie'], \array_column($secondPage['attributes'], 'key'));
    }

    public function testListAttributesRejectsAnUnsupportedSortField(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tool->listAttributes('en', sortBy: 'name');
    }

    public function testListAttributesReturnsErrorOnFailure(): void
    {
        $this->attributeGroupRepository->findBy(Argument::cetera())->willThrow(new \RuntimeException('DB gone'));

        $result = $this->tool->listAttributes('en');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['hint']);
    }

    public function testListAttributesMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(AttributeListTool::class, 'listAttributes');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listAttributes() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_attribute_list', $instance->name);
    }

    private function addAttribute(AttributeGroup $group, int $id, string $key, string $type, string $name): Attribute
    {
        $attribute = new Attribute($group);
        (new \ReflectionProperty($attribute, 'id'))->setValue($attribute, $id);
        $attribute->setKey($key);
        $attribute->setType($type);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));

        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attribute));

        return $attribute;
    }
}
