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
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\AttributeListTool;

#[CoversClass(AttributeListTool::class)]
final class AttributeListToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;
    private AttributeListTool $tool;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $this->tool = new AttributeListTool($this->attributeRepository->reveal());
    }

    public function testListAttributesExposesTheUuidUsedAsAttributeKey(): void
    {
        $group = new AttributeGroup('group-uuid');
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'Appearance'));

        $colour = $this->attribute($group, 'colour-uuid', 'colour', AttributeInterface::TYPE_TEXT, 'Colour');

        // Pinned: a lost select here costs queries per row and nothing else would notice.
        $this->attributeRepository->findBy([], [
            AttributeRepositoryInterface::SELECT_ATTRIBUTE_TRANSLATIONS => true,
            AttributeRepositoryInterface::SELECT_ATTRIBUTE_GROUP => true,
            AttributeRepositoryInterface::SELECT_ATTRIBUTE_OPTIONS => true,
        ])->willReturn([$colour])->shouldBeCalledOnce();

        $result = $this->tool->listAttributes('en');

        $this->assertSame(1, $result['total']);

        $this->assertIsArray($result['attributes']);
        $attribute = $result['attributes'][0];
        $this->assertIsArray($attribute);
        $this->assertSame('Appearance', $attribute['group']);
        $this->assertSame('colour-uuid', $attribute['id']);
        $this->assertSame('group-uuid', $attribute['groupUuid']);
        $this->assertSame('colour', $attribute['key']);
        $this->assertSame(AttributeInterface::TYPE_TEXT, $attribute['type']);
        $this->assertSame('Colour', $attribute['name']);
        $this->assertArrayNotHasKey('options', $attribute);
    }

    public function testListAttributesIncludesOptionsForOptionAttributes(): void
    {
        $group = new AttributeGroup();
        $attribute = $this->attribute($group, 'size-uuid', 'size', AttributeInterface::TYPE_OPTIONS, 'Size');

        $option = new AttributeOption($attribute, 'xl');
        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Extra Large'));
        $attribute->addOption($option);

        $this->attributeRepository->findBy(Argument::cetera())->willReturn([$attribute]);

        $result = $this->tool->listAttributes('en');

        $this->assertIsArray($result['attributes']);
        $attribute = $result['attributes'][0];
        $this->assertIsArray($attribute);
        $this->assertSame([['key' => 'xl', 'name' => 'Extra Large']], $attribute['options']);
    }

    public function testListAttributesFallsBackToTheKeyWhenALocaleIsMissing(): void
    {
        $group = new AttributeGroup();
        $material = $this->attribute($group, 'material-uuid', 'material', AttributeInterface::TYPE_TEXT, 'Material');

        $this->attributeRepository->findBy(Argument::cetera())->willReturn([$material]);

        $result = $this->tool->listAttributes('de');

        $this->assertIsArray($result['attributes']);
        $attribute = $result['attributes'][0];
        $this->assertIsArray($attribute);
        $this->assertSame('material', $attribute['name']);
    }

    public function testListAttributesSortsAndPagesTheFlattenedList(): void
    {
        $group = new AttributeGroup();
        $this->attributeRepository->findBy(Argument::cetera())->willReturn([
            $this->attribute($group, 'alpha-uuid', 'alpha', AttributeInterface::TYPE_TEXT, 'Alpha'),
            $this->attribute($group, 'bravo-uuid', 'bravo', AttributeInterface::TYPE_TEXT, 'Bravo'),
            $this->attribute($group, 'charlie-uuid', 'charlie', AttributeInterface::TYPE_TEXT, 'Charlie'),
        ]);

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
        $this->attributeRepository->findBy(Argument::cetera())->willThrow(new \RuntimeException('DB gone'));

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

    private function attribute(AttributeGroup $group, string $uuid, string $key, string $type, string $name): Attribute
    {
        $attribute = new Attribute($group, $uuid);
        $attribute->setKey($key);
        $attribute->setType($type);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));

        return $attribute;
    }

    public function testListAttributesRejectsAnUnsupportedSortOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tool->listAttributes('en', sortOrder: 'sideways');
    }
}
