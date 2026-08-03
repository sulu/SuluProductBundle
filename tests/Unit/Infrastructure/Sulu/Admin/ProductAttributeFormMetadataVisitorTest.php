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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
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
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeFieldFactory;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributeFormMetadataVisitor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductAttributeFormMetadataVisitor::class)]
class ProductAttributeFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    /** @var ObjectProphecy<FormMetadataLoaderInterface> */
    private ObjectProphecy $formMetadataLoader;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
    }

    private function visitor(): ProductAttributeFormMetadataVisitor
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

        return new ProductAttributeFormMetadataVisitor(
            $this->productFamilyRepository->reveal(),
            new AttributeFieldFactory(
                new AttributeTypeRegistry([new NumberAttributeType()]),
                $this->formMetadataLoader->reveal(),
                new MeasurementRegistry(),
                $translator,
            ),
            new PropertyMetadataMapperRegistry($mapperContainer),
            $translator,
            $this->productRepository->reveal(),
        );
    }

    private function group(int $id = 1, ?string $name = 'Dimensions'): AttributeGroupInterface
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

    public function testIgnoresOtherForms(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_family_details');  // not product_details

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        self::assertSame([], $form->getItems());
    }

    public function testNoIdInjectsNothing(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_details');

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
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_1', $items);
        $section = $items['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Dimensions', $section->getLabel('en'));
        $sectionItems = $section->getItems();
        self::assertArrayHasKey('attributes/7', $sectionItems);
        $field = $sectionItems['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $field);
        self::assertSame('number', $field->getType());
        self::assertSame('Weight', $field->getLabel('en'));
        self::assertSame('Weight in kilograms', $field->getDescription('en'));
        self::assertTrue($field->isRequired());
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
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());
        $attribute->getGroup()->willReturn($this->group(9, $groupName));

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_9', $items);
        $section = $items['attribute_group_9'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Attributes', $section->getLabel('en'));
    }

    public function testUsesDefaultLocaleGroupNameWhenLocaleTranslationMissing(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $groupTranslation = $this->prophesize(AttributeGroupTranslationInterface::class);
        $groupTranslation->getName()->willReturn('Abmessungen');

        $group = $this->prophesize(AttributeGroupInterface::class);
        $group->getId()->willReturn(3);
        $group->getTranslation('en')->willReturn(null);
        $group->getDefaultLocale()->willReturn('de');
        $group->getTranslation('de')->willReturn($groupTranslation->reveal());

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn($translation->reveal());
        $attribute->getGroup()->willReturn($group->reveal());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_3', $items);
        $section = $items['attribute_group_3'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Abmessungen', $section->getLabel('en'));
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
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $schema = $form->getSchema()->toJsonSchema();

        self::assertSame([
            'allOf' => [
                ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']],
                [
                    'type' => 'object',
                    'properties' => [
                        'attributes' => [
                            'type' => 'object',
                            'properties' => [
                                7 => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 10.0],
                            ],
                            'required' => ['7'],
                        ],
                    ],
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
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $field = $section->getItems()['attributes/7'];
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

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());

        $form = new FormMetadata();
        $form->setKey('product_details');

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

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn(null);

        $form = new FormMetadata();
        $form->setKey('product_details');

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
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());

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
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $injected = $section->getItems()['attributes/7'];
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

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());

        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $other = new FieldMetadata('other');
        $other->setType('number');
        $fragment->addItem($other);
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn($fragment);

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        self::assertSame([], $form->getItems());
    }

    public function testInjectsUnitFieldDerivedFromStoredUnit(): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn(['unit' => 'KILOGRAM']);
        $attribute->getTranslation('en')->willReturn($translation->reveal());
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $sectionItems = $section->getItems();

        $valueField = $sectionItems['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $valueField);
        self::assertSame(8, $valueField->getColSpan());

        self::assertArrayHasKey('attributes/7_unit', $sectionItems);
        $unitField = $sectionItems['attributes/7_unit'];
        self::assertInstanceOf(FieldMetadata::class, $unitField);
        self::assertSame('single_select', $unitField->getType());
        self::assertSame(4, $unitField->getColSpan());
        self::assertSame('true', $unitField->getDisabledCondition());
        self::assertSame('Unit', $unitField->getLabel('en'));

        $valuesOption = $unitField->getOptions()['values'];
        /** @var OptionMetadata[] $valueOptions */
        $valueOptions = $valuesOption->getValue();
        self::assertCount(1, $valueOptions);
        self::assertSame('KILOGRAM', $valueOptions[0]->getName());
        self::assertSame('KILOGRAM', $valueOptions[0]->getValue());
        self::assertSame('kg', $valueOptions[0]->getTitle('en'));
    }

    public function testDoesNotInjectUnitFieldWhenUnitMissing(): void
    {
        $this->assertNoUnitFieldForConfig([]);
    }

    public function testDoesNotInjectUnitFieldWhenUnitUnknown(): void
    {
        $this->assertNoUnitFieldForConfig(['unit' => 'NON_EXISTENT_UNIT']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assertNoUnitFieldForConfig(array $config): void
    {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Weight');
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn($config);
        $attribute->getTranslation('en')->willReturn($translation->reveal());
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $sectionItems = $section->getItems();
        self::assertArrayHasKey('attributes/7', $sectionItems);
        self::assertArrayNotHasKey('attributes/7_unit', $sectionItems);
        self::assertSame(12, $sectionItems['attributes/7']->getColSpan());
    }

    public function testInjectsNothingWhenNoFamilyFound(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'missing'])->willReturn(null);

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'missing']);

        self::assertSame([], $form->getItems());
    }

    public function testUsesDefaultLocaleTranslationWhenLocaleTranslationMissing(): void
    {
        $fallbackTranslation = $this->prophesize(AttributeTranslationInterface::class);
        $fallbackTranslation->getName()->willReturn('Gewicht');
        $fallbackTranslation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('weight');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn([]);
        $attribute->getTranslation('en')->willReturn(null);
        $attribute->getDefaultLocale()->willReturn('de');
        $attribute->getTranslation('de')->willReturn($fallbackTranslation->reveal());
        $attribute->getGroup()->willReturn($this->group());

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $field = $section->getItems()['attributes/7'];
        self::assertInstanceOf(FieldMetadata::class, $field);
        self::assertSame('Gewicht', $field->getLabel('en'));
    }

    public function testSkipsVariantAttributesForVariantProduct(): void
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
        $attribute->getGroup()->willReturn($this->group());

        $nonVariantFamilyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $nonVariantFamilyAttribute->isVariantSpecific()->willReturn(false);
        $nonVariantFamilyAttribute->getAttribute()->willReturn($attribute->reveal());
        $nonVariantFamilyAttribute->isRequired()->willReturn(false);

        $variantFamilyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $variantFamilyAttribute->isVariantSpecific()->willReturn(true);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([
            $nonVariantFamilyAttribute->reveal(),
            $variantFamilyAttribute->reveal(),
        ]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $product = $this->prophesize(Product::class);
        $product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)->willReturn(true);
        $this->productRepository->findOneBy(['uuid' => 'uuid-1'])->willReturn($product->reveal());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        $sectionItems = $section->getItems();
        self::assertArrayHasKey('attributes/7', $sectionItems);
        self::assertArrayNotHasKey('attributes/8', $sectionItems);
    }

    public function testGroupsAttributesIntoSeparateSectionsPerGroup(): void
    {
        $translation7 = $this->prophesize(AttributeTranslationInterface::class);
        $translation7->getName()->willReturn('Weight');
        $translation7->getDescription()->willReturn(null);

        $attribute7 = $this->prophesize(AttributeInterface::class);
        $attribute7->getId()->willReturn(7);
        $attribute7->getKey()->willReturn('weight');
        $attribute7->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute7->getConfig()->willReturn([]);
        $attribute7->getTranslation('en')->willReturn($translation7->reveal());
        $attribute7->getGroup()->willReturn($this->group(1, 'Dimensions'));

        $familyAttribute7 = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute7->getAttribute()->willReturn($attribute7->reveal());
        $familyAttribute7->isRequired()->willReturn(false);

        $translation8 = $this->prophesize(AttributeTranslationInterface::class);
        $translation8->getName()->willReturn('Voltage');
        $translation8->getDescription()->willReturn(null);

        $attribute8 = $this->prophesize(AttributeInterface::class);
        $attribute8->getId()->willReturn(8);
        $attribute8->getKey()->willReturn('voltage');
        $attribute8->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute8->getConfig()->willReturn([]);
        $attribute8->getTranslation('en')->willReturn($translation8->reveal());
        $attribute8->getGroup()->willReturn($this->group(2, 'Electrical'));

        $familyAttribute8 = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute8->getAttribute()->willReturn($attribute8->reveal());
        $familyAttribute8->isRequired()->willReturn(false);

        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([
            $familyAttribute7->reveal(),
            $familyAttribute8->reveal(),
        ]);

        $this->productFamilyRepository->findOneBy(['productUuid' => 'uuid-1'])->willReturn($family->reveal());
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());

        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'uuid-1']);

        $items = $form->getItems();
        self::assertArrayHasKey('attribute_group_1', $items);
        self::assertArrayHasKey('attribute_group_2', $items);

        $sectionOne = $items['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $sectionOne);
        self::assertArrayHasKey('attributes/7', $sectionOne->getItems());
        self::assertArrayNotHasKey('attributes/8', $sectionOne->getItems());

        $sectionTwo = $items['attribute_group_2'];
        self::assertInstanceOf(SectionMetadata::class, $sectionTwo);
        self::assertArrayHasKey('attributes/8', $sectionTwo->getItems());
    }
}
