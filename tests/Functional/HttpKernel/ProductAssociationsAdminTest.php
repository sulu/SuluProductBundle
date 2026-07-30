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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataProvider;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAssociationsFormMetadataVisitor;

/**
 * The test application config (tests/Application/config/config.yml) configures the
 * "alternative" and "suitable" association types, which is required for the assertions
 * in this test to hold. It additionally declares "associations/suitable" in its own
 * tests/Application/config/forms/product_associations.xml, so "suitable" is the
 * project-declared field and "alternative" the generated one.
 */
#[CoversClass(ProductAssociationsFormMetadataVisitor::class)]
#[CoversClass(ProductAdmin::class)]
class ProductAssociationsAdminTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testFormMetadataExposesConfiguredAssociationTypes(): void
    {
        self::bootKernel();

        /** @var FormMetadataProvider $formMetadataProvider */
        $formMetadataProvider = self::getContainer()->get('sulu_admin.form_metadata_provider');

        $metadata = $formMetadataProvider->getMetadata('product_associations', 'en');
        $this->assertInstanceOf(FormMetadata::class, $metadata);

        $items = $metadata->getItems();

        $suitable = $items['associations/suitable'];
        $this->assertInstanceOf(FieldMetadata::class, $suitable);
        $this->assertSame('product_selection', $suitable->getType());

        $section = $items['associations'];
        $this->assertInstanceOf(SectionMetadata::class, $section);

        $sectionItems = $section->getItems();
        $this->assertSame(['associations/alternative'], \array_keys($sectionItems));

        $alternative = $sectionItems['associations/alternative'];
        $this->assertInstanceOf(FieldMetadata::class, $alternative);
        $this->assertSame('product_selection', $alternative->getType());
        $this->assertSame(12, $alternative->getColSpan());
        $this->assertSame('sulu_product.association_type_alternative', $alternative->getLabel('en'));

        // the "associations/" prefix is nested into an "associations" object by the schema pipeline
        $schema = \json_encode($metadata->getSchema()->toJsonSchema());
        $this->assertIsString($schema);
        $this->assertStringContainsString('"alternative"', $schema);
        $this->assertStringContainsString('"suitable"', $schema);
    }

    public function testDeclaredFieldAppearsOnceWithItsParams(): void
    {
        self::bootKernel();

        /** @var FormMetadataProvider $formMetadataProvider */
        $formMetadataProvider = self::getContainer()->get('sulu_admin.form_metadata_provider');

        $formMetadata = $formMetadataProvider->getMetadata('product_associations', 'en');
        $this->assertInstanceOf(FormMetadata::class, $formMetadata);

        $flatFields = $formMetadata->getFlatFieldMetadata();

        // exactly one field per configured type - the declared one was not regenerated
        $this->assertSame(['associations/suitable', 'associations/alternative'], \array_keys($flatFields));

        $declared = $flatFields['associations/suitable'];
        $this->assertSame('product_selection', $declared->getType());
        $this->assertSame('Suitable products', $declared->getLabel('en'));
        $this->assertNotNull($declared->findOption('properties'));

        $generated = $flatFields['associations/alternative'];
        $this->assertNull($generated->findOption('properties'));
    }

    public function testAdminRegistersAssociationsTabWhenTypesConfigured(): void
    {
        self::bootKernel();

        $admin = self::getContainer()->get('sulu_product.product_admin');

        $viewCollection = new ViewCollection();
        $admin->configureViews($viewCollection);

        $this->assertTrue($viewCollection->has(ProductAdmin::EDIT_TABS_VIEW . '.associations'));
        $this->assertFalse($viewCollection->has(ProductAdmin::ADD_TABS_VIEW . '.associations'));
    }
}
