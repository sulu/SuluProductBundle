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
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
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
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributeFormMetadataVisitor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductAttributeFormMetadataVisitor::class)]
class ProductAttributeFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    private const FAMILY_SELECT = [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true];

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    /** @var ObjectProphecy<FormMetadataLoaderInterface> */
    private ObjectProphecy $formMetadataLoader;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])
            ->willReturn($this->fragmentWithValueField());
    }

    private function visitor(): ProductAttributeFormMetadataVisitor
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Attributes');

        $mapperContainer = new Container();
        $mapperContainer->set('number', new NumberPropertyMetadataMapper());

        return new ProductAttributeFormMetadataVisitor(
            $this->productFamilyRepository->reveal(),
            new AttributeFieldFactory(
                new AttributeTypeRegistry([new NumberAttributeType()]),
                $this->formMetadataLoader->reveal(),
                new MeasurementRegistry(),
            ),
            $translator,
            new PropertyMetadataMapperRegistry($mapperContainer),
        );
    }

    private function form(): FormMetadata
    {
        $form = new FormMetadata();
        $form->setKey('product_attributes');

        return $form;
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

    private function group(int $id = 1, ?string $name = 'Dimensions', ?string $defaultLocale = null): AttributeGroupInterface
    {
        $group = $this->prophesize(AttributeGroupInterface::class);
        $group->getId()->willReturn($id);
        $group->getDefaultLocale()->willReturn($defaultLocale);

        $translation = null;
        if (null !== $name) {
            $translationProphecy = $this->prophesize(AttributeGroupTranslationInterface::class);
            $translationProphecy->getName()->willReturn($name);
            $translation = $translationProphecy->reveal();
        }

        $group->getTranslation('en')->willReturn($translation);
        if (null !== $defaultLocale) {
            $group->getTranslation($defaultLocale)->willReturn($translation);
        }

        return $group->reveal();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function familyAttribute(
        int $id,
        string $name,
        AttributeGroupInterface $group,
        bool $variantSpecific = false,
        bool $required = false,
        array $config = [],
    ): ProductFamilyAttributeInterface {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn($name);
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn($id);
        $attribute->getKey()->willReturn(\strtolower($name));
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->getConfig()->willReturn($config);
        $attribute->getDefaultLocale()->willReturn(null);
        $attribute->getTranslation('en')->willReturn($translation->reveal());
        $attribute->getGroup()->willReturn($group);

        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->isVariantSpecific()->willReturn($variantSpecific);
        $familyAttribute->isRequired()->willReturn($required);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());

        return $familyAttribute->reveal();
    }

    /**
     * @param list<ProductFamilyAttributeInterface> $familyAttributes
     */
    private function family(array $familyAttributes): ProductFamilyInterface
    {
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn($familyAttributes);

        return $family->reveal();
    }

    public function testIgnoresOtherForms(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        self::assertSame([], $form->getItems());
        self::assertTrue($form->isCacheable());
    }

    public function testNoSelectorInjectsNothingButStaysUncacheable(): void
    {
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', []);

        self::assertSame([], $form->getItems());
        self::assertFalse($form->isCacheable());
    }

    public function testUnknownFamilyInjectsNothing(): void
    {
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-x'], self::FAMILY_SELECT)->willReturn(null);
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-x']);

        self::assertSame([], $form->getItems());
        self::assertFalse($form->isCacheable());
    }

    public function testInjectsSharedAttributesGroupedBySection(): void
    {
        $dimensions = $this->group(1, 'Dimensions');
        $electrical = $this->group(2, 'Electrical');
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $dimensions, false, true),
            $this->familyAttribute(8, 'Colour', $dimensions, true),
            $this->familyAttribute(9, 'Voltage', $electrical, false, false, ['unit' => 'VOLT']),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        $items = $form->getItems();
        self::assertSame(['attribute_group_1', 'attribute_group_2'], \array_keys($items));

        $dimensionsSection = $items['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $dimensionsSection);
        self::assertSame('Dimensions', $dimensionsSection->getLabel('en'));
        self::assertSame(['attribute_7'], \array_keys($dimensionsSection->getItems()));
        $weight = $dimensionsSection->getItems()['attribute_7'];
        self::assertInstanceOf(FieldMetadata::class, $weight);
        self::assertSame('number', $weight->getType());
        self::assertSame('Weight', $weight->getLabel('en'));
        self::assertTrue($weight->isRequired());

        $electricalSection = $items['attribute_group_2'];
        self::assertInstanceOf(SectionMetadata::class, $electricalSection);
        $voltage = $electricalSection->getItems()['attribute_9'];
        self::assertInstanceOf(FieldMetadata::class, $voltage);
        self::assertSame('Voltage (V)', $voltage->getLabel('en'));

        self::assertFalse($form->isCacheable());
    }

    public function testSetsValidationSchemaKeyedByFieldName(): void
    {
        $dimensions = $this->group(1, 'Dimensions');
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $dimensions, false, true, ['min' => 0, 'max' => 10]),
            $this->familyAttribute(8, 'Colour', $dimensions, true),
            $this->familyAttribute(9, 'Voltage', $dimensions),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        self::assertSame([
            'allOf' => [
                ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']],
                [
                    'type' => 'object',
                    'properties' => [
                        'attribute_7' => ['type' => 'number', 'minimum' => 0.0, 'maximum' => 10.0],
                        'attribute_9' => ['anyOf' => [['type' => 'null'], ['type' => 'number']]],
                    ],
                    'required' => ['attribute_7'],
                ],
            ],
        ], $form->getSchema()->toJsonSchema());
    }

    public function testLeavesTheSchemaAloneWithoutAttributes(): void
    {
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        self::assertEquals(new SchemaMetadata(), $form->getSchema());
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function provideVariantFlags(): iterable
    {
        yield 'string true' => ['true'];
        yield 'string 1' => ['1'];
        yield 'bool true' => [true];
    }

    #[DataProvider('provideVariantFlags')]
    public function testVariantFlagKeepsOnlyAxisAttributes(mixed $variant): void
    {
        $group = $this->group();
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $group),
            $this->familyAttribute(8, 'Colour', $group, true),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1', 'variant' => $variant]);

        $section = $form->getItems()['attribute_group_1'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame(['attribute_8'], \array_keys($section->getItems()));
    }

    public function testResolvesFamilyThroughProductOption(): void
    {
        $group = $this->group();
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $group),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['product' => 'product-1']);

        self::assertSame(['attribute_group_1'], \array_keys($form->getItems()));
    }

    public function testProductFamilyOptionWinsOverProductOption(): void
    {
        $group = $this->group();
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $group),
        ]));
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->shouldNotBeCalled();
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1', 'product' => 'product-1']);

        self::assertSame(['attribute_group_1'], \array_keys($form->getItems()));
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
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $this->group(9, $groupName)),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        $section = $form->getItems()['attribute_group_9'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Attributes', $section->getLabel('en'));
    }

    public function testUsesDefaultLocaleGroupNameWhenLocaleTranslationMissing(): void
    {
        $groupTranslation = $this->prophesize(AttributeGroupTranslationInterface::class);
        $groupTranslation->getName()->willReturn('Abmessungen');

        $group = $this->prophesize(AttributeGroupInterface::class);
        $group->getId()->willReturn(3);
        $group->getTranslation('en')->willReturn(null);
        $group->getDefaultLocale()->willReturn('de');
        $group->getTranslation('de')->willReturn($groupTranslation->reveal());

        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $group->reveal()),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        $section = $form->getItems()['attribute_group_3'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertSame('Abmessungen', $section->getLabel('en'));
    }

    public function testSkipsAttributesWithoutFormFragment(): void
    {
        $this->formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn(null);
        $this->productFamilyRepository->findOneBy(['uuid' => 'family-1'], self::FAMILY_SELECT)->willReturn($this->family([
            $this->familyAttribute(7, 'Weight', $this->group()),
        ]));
        $form = $this->form();

        $this->visitor()->visitFormMetadata($form, 'en', ['productFamily' => 'family-1']);

        self::assertSame([], $form->getItems());
    }
}
