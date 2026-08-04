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
use PHPUnit\Framework\Attributes\DataProvider;
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
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslationInterface;
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
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => match ($id) {
                'sulu_product.attributes' => 'Attributes',
                default => 'Unit',
            },
        );

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

    private function group(int $id = 1, ?string $name = 'Color'): AttributeGroupInterface
    {
        $group = $this->prophesize(AttributeGroupInterface::class);
        $group->getId()->willReturn($id);
        $group->getDefaultLocale()->willReturn(null);

        $translation = null;
        if (null !== $name) {
            $translationProphecy = $this->prophesize(AttributeGroupTranslationInterface::class);
            $translationProphecy->getName()->willReturn($name);
            $translation = $translationProphecy->reveal();
        }

        $group->getTranslation('en')->willReturn($translation);

        return $group->reveal();
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
    private function attributeWithFamilyAttribute(int $id, string $key, string $name, bool $variant, bool $required = false, ?AttributeGroupInterface $group = null): array
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
        $attribute->getGroup()->willReturn($group ?? $this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn($required);
        $familyAttribute->isVariantSpecific()->willReturn($variant);

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
        self::assertArrayHasKey('attribute_group_1', $items);
        $section = $items['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Color', $section->getLabel('en'));
        $sectionItems = $section->getItems();

        self::assertArrayHasKey('attributes/7', $sectionItems);
        $variantField = $sectionItems['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $variantField);
        self::assertNotSame('true', $variantField->getDisabledCondition());

        self::assertArrayNotHasKey('attributes/8', $sectionItems);

        self::assertFalse($form->isCacheable());
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function provideMissingGroupNames(): iterable
    {
        yield 'no translation for locale' => [null];
        yield 'empty translated name' => [''];
    }

    #[DataProvider('provideMissingGroupNames')]
    public function testUsesGenericSectionLabelWhenGroupNameMissing(?string $groupName): void
    {
        [, $familyAttribute] = $this->attributeWithFamilyAttribute(7, 'color', 'Color', true, false, $this->group(9, $groupName));

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_9', $items);
        $section = $items['attribute_group_9'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Attributes', $section->getLabel('en'));
    }

    public function testUsesDefaultLocaleGroupNameWhenLocaleTranslationMissing(): void
    {
        $groupTranslation = $this->prophesize(AttributeGroupTranslationInterface::class);
        $groupTranslation->getName()->willReturn('Farbe');

        $group = $this->prophesize(AttributeGroupInterface::class);
        $group->getId()->willReturn(3);
        $group->getTranslation('en')->willReturn(null);
        $group->getDefaultLocale()->willReturn('de');
        $group->getTranslation('de')->willReturn($groupTranslation->reveal());

        [, $familyAttribute] = $this->attributeWithFamilyAttribute(7, 'color', 'Color', true, false, $group->reveal());

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_3', $items);
        $section = $items['attribute_group_3'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Farbe', $section->getLabel('en'));
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
        $familyAttribute->isVariantSpecific()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        self::assertArrayNotHasKey('attribute_group_1', $form->getItems());
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
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);
        $familyAttribute->isVariantSpecific()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_1', $items);
        $section = $items['attribute_group_1'];
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
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(true);
        $familyAttribute->isVariantSpecific()->willReturn(true);

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
        self::assertArrayHasKey('attribute_group_1', $items);
        $section = $items['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertArrayHasKey('attributes/13', $section->getItems());
        self::assertFalse($form->isCacheable());
    }

    public function testGroupsVariantAttributesIntoSeparateSectionsPerGroup(): void
    {
        $translation7 = $this->prophesize(AttributeTranslationInterface::class);
        $translation7->getName()->willReturn('Color');
        $translation7->getDescription()->willReturn(null);

        $attribute7 = $this->prophesize(AttributeInterface::class);
        $attribute7->getId()->willReturn(7);
        $attribute7->getKey()->willReturn('color');
        $attribute7->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute7->getConfig()->willReturn([]);
        $attribute7->getTranslation('en')->willReturn($translation7->reveal());
        $attribute7->getGroup()->willReturn($this->group(1, 'Color'));

        $familyAttribute7 = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute7->getAttribute()->willReturn($attribute7->reveal());
        $familyAttribute7->isRequired()->willReturn(false);
        $familyAttribute7->isVariantSpecific()->willReturn(true);

        $translation8 = $this->prophesize(AttributeTranslationInterface::class);
        $translation8->getName()->willReturn('Size');
        $translation8->getDescription()->willReturn(null);

        $attribute8 = $this->prophesize(AttributeInterface::class);
        $attribute8->getId()->willReturn(8);
        $attribute8->getKey()->willReturn('size');
        $attribute8->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute8->getConfig()->willReturn([]);
        $attribute8->getTranslation('en')->willReturn($translation8->reveal());
        $attribute8->getGroup()->willReturn($this->group(2, 'Size'));

        $familyAttribute8 = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute8->getAttribute()->willReturn($attribute8->reveal());
        $familyAttribute8->isRequired()->willReturn(false);
        $familyAttribute8->isVariantSpecific()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([
            $familyAttribute7->reveal(),
            $familyAttribute8->reveal(),
        ]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'parent-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_1', $items);
        self::assertArrayHasKey('attribute_group_2', $items);

        $sectionOne = $items['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $sectionOne);
        self::assertArrayHasKey('attributes/7', $sectionOne->getItems());

        $sectionTwo = $items['attribute_group_2'];
        self::assertInstanceOf(SectionMetadata::class, $sectionTwo);
        self::assertArrayHasKey('attributes/8', $sectionTwo->getItems());
    }
}
