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
 * in this test to hold.
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

        $section = $metadata->getItems()['associations'];
        $this->assertInstanceOf(SectionMetadata::class, $section);

        $sectionItems = $section->getItems();
        $this->assertArrayHasKey('associations/alternative', $sectionItems);
        $this->assertArrayHasKey('associations/suitable', $sectionItems);

        $alternative = $sectionItems['associations/alternative'];
        $this->assertInstanceOf(FieldMetadata::class, $alternative);
        $this->assertSame('product_selection', $alternative->getType());
        $this->assertSame(12, $alternative->getColSpan());

        $suitable = $sectionItems['associations/suitable'];
        $this->assertInstanceOf(FieldMetadata::class, $suitable);
        $this->assertSame('product_selection', $suitable->getType());

        $schema = $metadata->getSchema()->toJsonSchema();
        $this->assertNotSame([], $schema);
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
