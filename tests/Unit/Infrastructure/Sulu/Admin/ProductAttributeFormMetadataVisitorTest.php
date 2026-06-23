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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapper\NumberPropertyMetadataMapper;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributeFormMetadataVisitor;
use Symfony\Component\DependencyInjection\Container;

#[CoversClass(ProductAttributeFormMetadataVisitor::class)]
class ProductAttributeFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    /** @var ObjectProphecy<FormMetadataLoaderInterface> */
    private ObjectProphecy $formMetadataLoader;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
    }

    private function visitor(): ProductAttributeFormMetadataVisitor
    {
        $mapperContainer = new Container();
        $mapperContainer->set('number', new NumberPropertyMetadataMapper());

        return new ProductAttributeFormMetadataVisitor(
            $this->productFamilyRepository->reveal(),
            new AttributeTypeRegistry([new NumberAttributeType()]),
            $this->formMetadataLoader->reveal(),
            new PropertyMetadataMapperRegistry($mapperContainer),
        );
    }

    private function fragmentWithValueField(): FormMetadata
    {
        $field = new FieldMetadata('value');
        $field->setType('number');
        $field->setColSpan(12);

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $fragment->addItem($field);

        return $fragment;
    }

    public function testIgnoresOtherForms(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        self::assertSame([], $form->getItems());
    }

    public function testNoIdInjectsNothing(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', []);

        self::assertSame([], $form->getItems());
    }

    public function testInjectsFieldPerEnabledFamilyAttribute(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn('Weight in kilograms');

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attributes/7', $items);
        $field = $items['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $field);
        self::assertSame('number', $field->getType());
        self::assertSame('Weight', $field->getLabel('en'));
        self::assertSame('Weight in kilograms', $field->getDescription('en'));
        self::assertTrue($field->isRequired());
        self::assertFalse($form->isCacheable());
    }

    public function testInjectsValidationSchemaForAttributes(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn(['min' => 0, 'max' => 10]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $schema = $form->getSchema()->toJsonSchema();

        self::assertSame([
            'allOf' => [
                ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']],
                [
                    'type' => 'object',
                    'properties' => [
                        'attributes/7' => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 10.0],
                    ],
                    'required' => ['attributes/7'],
                ],
            ],
        ], $schema);
    }

    public function testDoesNotSetDescriptionWhenAttributeHasNone(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attributes/7', $items);
        $field = $items['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $field);
        self::assertNull($field->getDescription('en'));
    }

    public function testSkipsAttributeWithUnknownType(): void
    {
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getType()->willReturn('unknown_type');
        $attribute->getConfig()->willReturn([]);

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        self::assertSame([], $form->getItems());
    }

    public function testSkipsAttributeWhenFragmentIsNotFormMetadata(): void
    {
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn(null);

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        self::assertSame([], $form->getItems());
    }

    public function testCloneCopiesFragmentOptionsAndTypes(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());

        $field = new FieldMetadata('value');
        $field->setType('single_select');
        $option = new OptionMetadata();
        $option->setName('opt');
        $field->addOption($option);
        $blockType = new FormMetadata();
        $blockType->setKey('some_block');
        $field->addType($blockType);

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $fragment->addItem($field);
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn($fragment);

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attributes/7', $items);
        $injected = $items['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $injected);
        self::assertArrayHasKey('opt', $injected->getOptions());
        self::assertCount(1, $injected->getTypes());
    }

    public function testSkipsAttributeWhenFragmentHasNoValueField(): void
    {
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->getOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $other = new FieldMetadata('other');
        $other->setType('number');
        $fragment->addItem($other);
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn($fragment);

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        self::assertSame([], $form->getItems());
    }

    public function testThrowsWhenNoFamilyFound(): void
    {
        $this->productFamilyRepository->getOneBy(['productUuid' => 'missing'])
            ->willThrow(new ProductFamilyNotFoundException(['productUuid' => 'missing']));

        $form = new FormMetadata();
        $form->setKey('product_attributes');

        $this->expectException(ProductFamilyNotFoundException::class);

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'missing']);
    }
}
