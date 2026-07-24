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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapper\SelectionPropertyMetadataMapper;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMinMaxValueResolver;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAssociationsFormMetadataVisitor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductAssociationsFormMetadataVisitor::class)]
final class ProductAssociationsFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    private function visitor(ProductAssociationTypeRegistry $registry): ProductAssociationsFormMetadataVisitor
    {
        $mapperContainer = new Container();
        $mapperContainer->set('product_selection', new SelectionPropertyMetadataMapper(new PropertyMetadataMinMaxValueResolver()));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProductAssociationsFormMetadataVisitor(
            $registry,
            new PropertyMetadataMapperRegistry($mapperContainer),
            $translator,
        );
    }

    private function registryWithTypes(): ProductAssociationTypeRegistry
    {
        return new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
            'suitable' => ['label' => 'sulu_product.association_type_suitable'],
        ]);
    }

    public function testInjectsFieldPerAssociationType(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_associations');

        $this->visitor($this->registryWithTypes())->visitFormMetadata($form, 'en', []);

        $items = $form->getItems();
        self::assertArrayHasKey('associations', $items);
        $section = $items['associations'];
        self::assertInstanceOf(SectionMetadata::class, $section);

        $sectionItems = $section->getItems();
        self::assertArrayHasKey('associations/alternative', $sectionItems);
        self::assertArrayHasKey('associations/suitable', $sectionItems);

        $alternative = $sectionItems['associations/alternative'];
        self::assertInstanceOf(FieldMetadata::class, $alternative);
        self::assertSame('product_selection', $alternative->getType());
        self::assertSame(12, $alternative->getColSpan());
        self::assertSame('sulu_product.association_type_alternative', $alternative->getLabel('en'));

        $suitable = $sectionItems['associations/suitable'];
        self::assertInstanceOf(FieldMetadata::class, $suitable);
        self::assertSame('product_selection', $suitable->getType());
        self::assertSame(12, $suitable->getColSpan());
        self::assertSame('sulu_product.association_type_suitable', $suitable->getLabel('en'));

        self::assertFalse($form->isCacheable());
    }

    public function testMergesSchemaPropertiesForAssociationTypes(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_associations');

        $this->visitor($this->registryWithTypes())->visitFormMetadata($form, 'en', []);

        $schema = $form->getSchema()->toJsonSchema();

        self::assertSame([
            'allOf' => [
                ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']],
                [
                    'type' => 'object',
                    'properties' => [
                        'associations' => [
                            'type' => 'object',
                            'properties' => [
                                'alternative' => [
                                    'anyOf' => [
                                        ['type' => 'null'],
                                        [
                                            'type' => 'array',
                                            'items' => ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']],
                                            'maxItems' => 0,
                                        ],
                                        [
                                            'type' => 'array',
                                            'items' => [
                                                'anyOf' => [
                                                    ['type' => 'string'],
                                                    ['type' => 'number'],
                                                ],
                                            ],
                                            'uniqueItems' => true,
                                        ],
                                    ],
                                ],
                                'suitable' => [
                                    'anyOf' => [
                                        ['type' => 'null'],
                                        [
                                            'type' => 'array',
                                            'items' => ['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']],
                                            'maxItems' => 0,
                                        ],
                                        [
                                            'type' => 'array',
                                            'items' => [
                                                'anyOf' => [
                                                    ['type' => 'string'],
                                                    ['type' => 'number'],
                                                ],
                                            ],
                                            'uniqueItems' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $schema);
    }

    public function testIgnoresOtherFormKeys(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_details');

        $this->visitor($this->registryWithTypes())->visitFormMetadata($form, 'en', []);

        self::assertSame([], $form->getItems());
    }

    public function testNoOpWhenNoAssociationTypesConfigured(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_associations');

        $this->visitor(new ProductAssociationTypeRegistry([]))->visitFormMetadata($form, 'en', []);

        self::assertSame([], $form->getItems());
    }

    public function testFallsBackToPlainPropertyMetadataWhenNoMapperRegistered(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $visitor = new ProductAssociationsFormMetadataVisitor(
            $this->registryWithTypes(),
            new PropertyMetadataMapperRegistry(new Container()),
            $translator,
        );

        $form = new FormMetadata();
        $form->setKey('product_associations');

        $visitor->visitFormMetadata($form, 'en', []);

        $section = $form->getItems()['associations'];
        self::assertInstanceOf(SectionMetadata::class, $section);
        self::assertArrayHasKey('associations/alternative', $section->getItems());
    }
}
