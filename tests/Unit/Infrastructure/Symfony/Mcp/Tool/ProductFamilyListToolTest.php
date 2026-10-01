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
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductFamilyListTool;

#[CoversClass(ProductFamilyListTool::class)]
final class ProductFamilyListToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;
    /** @var ObjectProphecy<FieldDescriptorFactoryInterface> */
    private ObjectProphecy $fieldDescriptorFactory;
    /** @var ObjectProphecy<DoctrineListBuilderFactoryInterface> */
    private ObjectProphecy $listBuilderFactory;
    /** @var ObjectProphecy<DoctrineListBuilder> */
    private ObjectProphecy $listBuilder;
    private ProductFamilyListTool $tool;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->fieldDescriptorFactory = $this->prophesize(FieldDescriptorFactoryInterface::class);
        $this->listBuilderFactory = $this->prophesize(DoctrineListBuilderFactoryInterface::class);
        $this->listBuilder = $this->prophesize(DoctrineListBuilder::class);

        $this->fieldDescriptorFactory->getFieldDescriptors(Argument::cetera())->willReturn([
            'id' => new DoctrineFieldDescriptor('id', 'id', 'FamilyEntity'),
            'name' => new DoctrineFieldDescriptor('name', 'name', 'FamilyEntity'),
        ]);

        $this->listBuilderFactory->create(Argument::cetera())->willReturn($this->listBuilder->reveal());

        $this->listBuilder->setIdField(Argument::cetera())->willReturn(null);
        $this->listBuilder->setParameter(Argument::cetera())->willReturn(null);
        $this->listBuilder->limit(Argument::cetera())->willReturn($this->listBuilder->reveal());
        $this->listBuilder->setCurrentPage(Argument::cetera())->willReturn(null);
        $this->listBuilder->addSelectField(Argument::cetera())->willReturn($this->listBuilder->reveal());
        $this->listBuilder->sort(Argument::cetera())->willReturn($this->listBuilder->reveal());

        $this->tool = new ProductFamilyListTool(
            $this->productFamilyRepository->reveal(),
            $this->fieldDescriptorFactory->reveal(),
            $this->listBuilderFactory->reveal(),
        );
    }

    public function testListFamiliesExposesAttributeFlagsThatDecideWhereAValueBelongs(): void
    {
        $this->listBuilder->execute()->willReturn([['id' => 'family-uuid', 'name' => 'Shirts']]);
        $this->listBuilder->count()->willReturn(1);

        $this->productFamilyRepository->findOneBy(['uuid' => 'family-uuid'])->willReturn($this->family());

        $result = $this->tool->listProductFamilies('en');

        $this->assertSame(1, $result['total']);
        $this->assertIsArray($result['families']);
        $family = $result['families'][0];
        $this->assertIsArray($family);
        $this->assertSame('family-uuid', $family['uuid']);
        $this->assertSame('Shirts', $family['name']);

        $attributes = $family['attributes'];
        $this->assertIsArray($attributes);
        $this->assertCount(2, $attributes);

        $byId = \array_column($attributes, null, 'attributeId');
        $this->assertIsArray($byId[10]);
        $this->assertTrue($byId[10]['required']);
        $this->assertFalse($byId[10]['variantSpecific']);
        $this->assertIsArray($byId[11]);
        $this->assertTrue($byId[11]['variantSpecific']);
        $this->assertSame('Colour', $byId[11]['name']);
    }

    public function testListFamiliesSetsPagingOnTheListBuilderNotFromTheRequest(): void
    {
        $this->listBuilder->execute()->willReturn([]);
        $this->listBuilder->count()->willReturn(0);

        $this->listBuilder->limit(5)->shouldBeCalledOnce()->willReturn($this->listBuilder->reveal());
        $this->listBuilder->setCurrentPage(3)->shouldBeCalledOnce()->willReturn(null);

        $this->tool->listProductFamilies('en', page: 3, limit: 5);
    }

    public function testListFamiliesPassesTheSortToTheListBuilder(): void
    {
        $this->listBuilder->execute()->willReturn([]);
        $this->listBuilder->count()->willReturn(0);

        $this->listBuilder->sort(Argument::cetera(), 'desc')->shouldBeCalledOnce()->willReturn($this->listBuilder->reveal());

        $this->tool->listProductFamilies('en', sortBy: 'name', sortOrder: 'desc');
    }

    public function testListFamiliesRejectsAnUnsupportedSortField(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tool->listProductFamilies('en', sortBy: 'uuid');
    }

    public function testListFamiliesReturnsErrorOnFailure(): void
    {
        $this->listBuilder->execute()->willThrow(new \RuntimeException('DB gone'));
        $this->listBuilder->count()->willReturn(0);

        $result = $this->tool->listProductFamilies('en');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['hint']);
    }

    public function testListProductFamiliesMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductFamilyListTool::class, 'listProductFamilies');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'listProductFamilies() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_family_list', $instance->name);
    }

    private function family(): ProductFamily
    {
        $family = new ProductFamily();
        $family->setUuid('family-uuid');

        $group = new AttributeGroup();

        foreach ([10 => ['material', 'Material', true, false], 11 => ['colour', 'Colour', false, true]] as $id => [$key, $name, $required, $variantSpecific]) {
            $attribute = new Attribute($group);
            (new \ReflectionProperty($attribute, 'id'))->setValue($attribute, $id);
            $attribute->setKey($key);
            $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setRequired($required);
            $familyAttribute->setVariantSpecific($variantSpecific);
            $family->addFamilyAttribute($familyAttribute);
        }

        return $family;
    }

    public function testListFamiliesRejectsAnUnsupportedSortOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->tool->listProductFamilies('en', sortOrder: 'sideways');
    }

    public function testListFamiliesSkipsMalformedRowsAndReportsNoAttributesForAVanishedFamily(): void
    {
        $this->listBuilder->execute()->willReturn([
            'not-a-row',
            ['name' => 'Row without an id'],
            ['id' => 'gone', 'name' => 'Gone'],
        ]);
        $this->listBuilder->count()->willReturn(3);

        $this->productFamilyRepository->findOneBy(['uuid' => 'gone'])->willReturn(null);

        $result = $this->tool->listProductFamilies('en');

        $this->assertSame(
            [['uuid' => 'gone', 'name' => 'Gone', 'attributes' => []]],
            $result['families'],
        );
    }
}
