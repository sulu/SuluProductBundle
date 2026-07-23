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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapper\NumberPropertyMetadataMapper;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeFieldFactory;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductVariantAttributeFormMetadataVisitor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductVariantAttributeFormMetadataVisitor::class)]
class ProductVariantAttributeFormMetadataVisitorTest extends TestCase
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

    private function visitor(): ProductVariantAttributeFormMetadataVisitor
    {
        $mapperContainer = new Container();
        $mapperContainer->set('number', new NumberPropertyMetadataMapper());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Unit');

        return new ProductVariantAttributeFormMetadataVisitor(
            $this->productFamilyRepository->reveal(),
            new AttributeFieldFactory(
                new AttributeTypeRegistry([new NumberAttributeType()]),
                $this->formMetadataLoader->reveal(),
                new MeasurementRegistry(),
                $translator,
            ),
            new PropertyMetadataMapperRegistry($mapperContainer),
            $translator,
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

    /**
     * @return array{0: ObjectProphecy<AttributeInterface>, 1: ObjectProphecy<ProductFamilyAttributeInterface>}
     */
    private function attributeWithFamilyAttribute(int $id, string $key, string $name, bool $variant, bool $required = false): array
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn($name);
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn($id);
        $attribute->getKey()->willReturn($key);
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn($required);
        $familyAttribute->isVariant()->willReturn($variant);

        return [$attribute, $familyAttribute];
    }

    public function testIgnoresOtherForms(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        self::assertSame([], $form->getItems());
    }

    public function testNoParentIdInjectsNothing(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', []);

        self::assertSame([], $form->getItems());
    }

    public function testInjectsNothingWhenNoFamilyFound(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'missing'])->willReturn(null);

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'missing']);

        self::assertSame([], $form->getItems());
    }

    public function testRendersOnlyVariantAttributeAndOmitsNonVariantAttribute(): void
    {
        [, $variantFamilyAttribute] = $this->attributeWithFamilyAttribute(7, 'color', 'Color', true);
        [, $nonVariantFamilyAttribute] = $this->attributeWithFamilyAttribute(8, 'weight', 'Weight', false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([
            $variantFamilyAttribute->reveal(),
            $nonVariantFamilyAttribute->reveal(),
        ]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attributes', $items);
        $section = $items['attributes'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $sectionItems = $section->getItems();

        self::assertArrayHasKey('attributes/7', $sectionItems);
        $variantField = $sectionItems['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $variantField);
        self::assertNotSame('true', $variantField->getDisabledCondition());

        self::assertArrayNotHasKey('attributes/8', $sectionItems);

        self::assertFalse($form->isCacheable());
    }

    public function testSkipsVariantAttributeWhenFieldCannotBeBuilt(): void
    {
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(9);
        $attribute->getKey()->willReturn('mystery');
        $attribute->getType()->willReturn('unregistered_type');
        $attribute->getConfig()->willReturn([]);

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isVariant()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        self::assertArrayNotHasKey('attributes', $form->getItems());
    }

    public function testAddsUnitFieldForVariantAttributeWithUnit(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(11);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn(['unit' => 'GRAM']);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);
        $familyAttribute->isVariant()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attributes', $items);
        $section = $items['attributes'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $sectionItems = $section->getItems();

        self::assertArrayHasKey('attributes/11', $sectionItems);
        self::assertArrayHasKey('attributes/11_unit', $sectionItems);
    }

    public function testUsesFallbackPropertyMetadataForUnmappedFieldType(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Color');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(13);
        $attribute->getKey()->willReturn('color');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(true);
        $familyAttribute->isVariant()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        // A fragment whose "value" field has a type with no registered PropertyMetadataMapper
        // ('number' is the only mapper), forcing the fallback PropertyMetadata branch.
        $valueField = new FieldMetadata('value');
        $valueField->setType('text_line');
        $valueField->setColSpan(12);
        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $fragment->addItem($valueField);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($fragment);

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attributes', $items);
        $section = $items['attributes'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertArrayHasKey('attributes/13', $section->getItems());
        self::assertFalse($form->isCacheable());
    }
}
