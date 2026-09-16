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
use Prophecy\Argument;
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
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeFieldFactory;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributesSchemaFormMetadataVisitor;
use Symfony\Component\DependencyInjection\Container;

#[CoversClass(ProductAttributesSchemaFormMetadataVisitor::class)]
class ProductAttributesSchemaFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    private const FAMILY_SELECT = [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true];

    private const ANY = ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']];

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
    }

    private function visitor(): ProductAttributesSchemaFormMetadataVisitor
    {
        $field = new FieldMetadata('value');
        $field->setType('number');
        $fragment = new FormMetadata();
        $fragment->setKey('product_attribute_number');
        $fragment->addItem($field);

        $formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
        $formMetadataLoader->getMetadata('product_attribute_number', 'en', [])->willReturn($fragment);

        $mapperContainer = new Container();
        $mapperContainer->set('number', new NumberPropertyMetadataMapper());

        return new ProductAttributesSchemaFormMetadataVisitor(
            $this->productFamilyRepository->reveal(),
            new AttributeFieldFactory(
                new AttributeTypeRegistry([new NumberAttributeType()]),
                $formMetadataLoader->reveal(),
                new MeasurementRegistry(),
                new PropertyMetadataMapperRegistry($mapperContainer),
            ),
        );
    }

    private function form(string $key): FormMetadata
    {
        $form = new FormMetadata();
        $form->setKey($key);

        return $form;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function familyAttribute(
        int $id,
        bool $variantSpecific = false,
        bool $required = false,
        array $config = [],
        string $type = AttributeInterface::TYPE_NUMBER,
    ): ProductFamilyAttributeInterface {
        $translation = $this->prophesize(AttributeTranslationInterface::class);
        $translation->getName()->willReturn('Attribute ' . $id);
        $translation->getDescription()->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn($id);
        $attribute->getKey()->willReturn('attribute_' . $id);
        $attribute->getType()->willReturn($type);
        $attribute->getConfig()->willReturn($config);
        $attribute->getDefaultLocale()->willReturn(null);
        $attribute->getTranslation('en')->willReturn($translation->reveal());

        // A real family attribute, so the tests run against its own availability rule.
        $familyAttribute = new ProductFamilyAttribute(
            $this->prophesize(ProductFamilyInterface::class)->reveal(),
            $attribute->reveal(),
        );
        $familyAttribute->setVariantSpecific($variantSpecific);
        $familyAttribute->setRequired($required);

        return $familyAttribute;
    }

    /**
     * @param list<ProductFamilyAttributeInterface> $familyAttributes
     */
    private function family(?string $uuid, array $familyAttributes): ProductFamilyInterface
    {
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getUuid()->willReturn($uuid);
        $family->getFamilyAttributes()->willReturn($familyAttributes);

        return $family->reveal();
    }

    public function testIgnoresOtherForms(): void
    {
        $form = $this->form('page');

        $this->visitor()->visitFormMetadata($form, 'en');

        self::assertTrue($form->isCacheable());
        self::assertEquals(new SchemaMetadata(), $form->getSchema());
    }

    public function testDetailsFormGetsOneConditionalSchemaPerFamily(): void
    {
        $this->productFamilyRepository->findBy([], self::FAMILY_SELECT)->willReturn([
            $this->family('family-1', [
                $this->familyAttribute(7, false, true, ['min' => 0, 'max' => 10]),
                $this->familyAttribute(8, true),
                $this->familyAttribute(9),
            ]),
            $this->family('family-2', [$this->familyAttribute(10)]),
            $this->family(null, [$this->familyAttribute(11)]),
        ]);
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en');

        $seven = ['type' => 'number', 'minimum' => 0.0, 'maximum' => 10.0];
        $nullableNumber = ['anyOf' => [['type' => 'null'], ['type' => 'number']]];

        self::assertFalse($form->isCacheable());
        self::assertSame([
            'allOf' => [
                self::ANY,
                [
                    'allOf' => [
                        self::branch('family-1', 'product', [
                            'type' => 'object',
                            'properties' => ['7' => $seven, '8' => $nullableNumber, '9' => $nullableNumber],
                            'required' => ['7'],
                        ], true),
                        self::branch('family-1', 'product_with_variants', [
                            'type' => 'object',
                            'properties' => ['7' => $seven, '9' => $nullableNumber],
                            'required' => ['7'],
                        ], true),
                        self::branch('family-2', 'product', ['type' => 'object', 'properties' => ['10' => $nullableNumber]]),
                        self::branch('family-2', 'product_with_variants', ['type' => 'object', 'properties' => ['10' => $nullableNumber]]),
                    ],
                ],
            ],
        ], $form->getSchema()->toJsonSchema());
    }

    public function testDetailsFormRequiresAVariantAttributeOnlyOnAProductWithoutVariants(): void
    {
        $this->productFamilyRepository->findBy([], self::FAMILY_SELECT)->willReturn([
            $this->family('family-1', [$this->familyAttribute(8, true, true)]),
        ]);
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en');

        self::assertSame([
            'allOf' => [
                self::ANY,
                [
                    'allOf' => [
                        self::branch('family-1', 'product', [
                            'type' => 'object',
                            'properties' => ['8' => ['type' => 'number']],
                            'required' => ['8'],
                        ], true),
                    ],
                ],
            ],
        ], $form->getSchema()->toJsonSchema(), 'a product with variants holds no variant attribute, so it gets no branch');
    }

    public function testDetailsFormScopesTheSchemaToTheProductsOwnFamily(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn(
            $this->family('family-1', [$this->familyAttribute(10)]),
        );
        $this->productFamilyRepository->findBy(Argument::cetera())->shouldNotBeCalled();
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'product-1']);

        $attributes = ['type' => 'object', 'properties' => ['10' => ['anyOf' => [['type' => 'null'], ['type' => 'number']]]]];

        self::assertSame([
            'allOf' => [
                self::ANY,
                [
                    'allOf' => [
                        self::branch('family-1', 'product', $attributes),
                        self::branch('family-1', 'product_with_variants', $attributes),
                    ],
                ],
            ],
        ], $form->getSchema()->toJsonSchema(), 'the edit form carries one branch, not one per family');
    }

    public function testDetailsFormWithUnknownProductKeepsTheSchema(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn(null);
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en', ['id' => 'product-1']);

        self::assertFalse($form->isCacheable());
        self::assertEquals(new SchemaMetadata(), $form->getSchema());
    }

    public function testDetailsFormWithoutFamiliesKeepsTheSchema(): void
    {
        $this->productFamilyRepository->findBy([], self::FAMILY_SELECT)->willReturn([]);
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en');

        self::assertFalse($form->isCacheable());
        self::assertEquals(new SchemaMetadata(), $form->getSchema());
    }

    public function testVariantFormGetsTheAxisAttributesOfTheParentFamily(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn(
            $this->family('family-1', [
                $this->familyAttribute(7, false, true),
                $this->familyAttribute(8, true, true),
            ]),
        );
        $form = $this->form('product_variant');

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'product-1']);

        self::assertFalse($form->isCacheable());
        self::assertSame([
            'allOf' => [
                self::ANY,
                [
                    'type' => 'object',
                    'properties' => [
                        'attributes' => [
                            'type' => 'object',
                            'properties' => ['8' => ['type' => 'number']],
                            'required' => ['8'],
                        ],
                    ],
                    'required' => ['attributes'],
                ],
            ],
        ], $form->getSchema()->toJsonSchema());
    }

    public function testVariantFormKeepsTheAttributesSectionForAxisAttributes(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn(
            $this->family('family-1', [$this->familyAttribute(8, true)]),
        );
        $form = $this->variantFormWithAttributesSection();

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'product-1']);

        self::assertSame(['title', 'attributes'], \array_keys($form->getItems()));
    }

    public function testVariantFormDropsTheAttributesSectionWithoutAxisAttributes(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn(
            $this->family('family-1', [$this->familyAttribute(7)]),
        );
        $form = $this->variantFormWithAttributesSection();

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'product-1']);

        self::assertSame(['title'], \array_keys($form->getItems()));
        self::assertEquals(new SchemaMetadata(), $form->getSchema());
    }

    public function testVariantFormWithoutParentKeepsTheSchema(): void
    {
        $form = $this->variantFormWithAttributesSection();

        $this->visitor()->visitFormMetadata($form, 'en');

        self::assertFalse($form->isCacheable());
        self::assertEquals(new SchemaMetadata(), $form->getSchema());
        self::assertSame(['title', 'attributes'], \array_keys($form->getItems()));
    }

    public function testVariantFormWithUnknownParentKeepsTheSchema(): void
    {
        $this->productFamilyRepository->findOneBy(['productUuid' => 'product-1'], self::FAMILY_SELECT)->willReturn(null);
        $form = $this->variantFormWithAttributesSection();

        $this->visitor()->visitFormMetadata($form, 'en', ['parentId' => 'product-1']);

        self::assertFalse($form->isCacheable());
        self::assertEquals(new SchemaMetadata(), $form->getSchema());
        self::assertSame(['title', 'attributes'], \array_keys($form->getItems()));
    }

    private function variantFormWithAttributesSection(): FormMetadata
    {
        $form = $this->form('product_variant');
        $form->setItems([
            'title' => new FieldMetadata('title'),
            'attributes' => new SectionMetadata('attributes'),
        ]);

        return $form;
    }

    public function testDetailsFormSkipsTheProductWithVariantsBranchOfAFamilyWithoutSharedAttributes(): void
    {
        $this->productFamilyRepository->findBy([], self::FAMILY_SELECT)->willReturn([
            $this->family('family-axis-only', [$this->familyAttribute(7, true)]),
            $this->family('family-2', [$this->familyAttribute(10)]),
        ]);
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en');

        $nullableNumber = ['anyOf' => [['type' => 'null'], ['type' => 'number']]];

        self::assertSame([
            'allOf' => [
                self::ANY,
                [
                    'allOf' => [
                        self::branch('family-axis-only', 'product', ['type' => 'object', 'properties' => ['7' => $nullableNumber]]),
                        self::branch('family-2', 'product', ['type' => 'object', 'properties' => ['10' => $nullableNumber]]),
                        self::branch('family-2', 'product_with_variants', ['type' => 'object', 'properties' => ['10' => $nullableNumber]]),
                    ],
                ],
            ],
        ], $form->getSchema()->toJsonSchema(), 'a family whose attributes are all axis attributes gets no product with variants branch');
    }

    public function testSkipsAnAttributeWhoseTypeHasNoField(): void
    {
        $this->productFamilyRepository->findBy([], self::FAMILY_SELECT)->willReturn([
            $this->family('family-1', [
                $this->familyAttribute(7, false, false, [], AttributeInterface::TYPE_TEXT),
                $this->familyAttribute(10),
            ]),
        ]);
        $form = $this->form('product_details');

        $this->visitor()->visitFormMetadata($form, 'en');

        $attributes = ['type' => 'object', 'properties' => ['10' => ['anyOf' => [['type' => 'null'], ['type' => 'number']]]]];

        self::assertSame([
            'allOf' => [
                self::ANY,
                [
                    'allOf' => [
                        self::branch('family-1', 'product', $attributes),
                        self::branch('family-1', 'product_with_variants', $attributes),
                    ],
                ],
            ],
        ], $form->getSchema()->toJsonSchema(), 'an attribute whose type has no form fragment is left out of the schema');
    }

    /**
     * @param array<string, mixed> $attributes the schema of the "attributes" property
     *
     * @return array<string, mixed>
     */
    private static function branch(string $family, string $productType, array $attributes, bool $mandatory = false): array
    {
        $then = ['type' => 'object', 'properties' => ['attributes' => $attributes]];
        if ($mandatory) {
            $then['required'] = ['attributes'];
        }

        return [
            'if' => [
                'type' => 'object',
                'properties' => ['productFamily' => ['const' => $family], 'type' => ['const' => $productType]],
                'required' => ['productFamily', 'type'],
            ],
            'then' => $then,
        ];
    }
}
