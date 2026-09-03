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
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadata;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductsListMetadataVisitor;

#[CoversClass(ProductsListMetadataVisitor::class)]
class ProductsListFiltersTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testProductFamilyIsFilterableAgainstTheFamilyList(): void
    {
        $field = $this->listMetadata()->getField('productFamily');

        $this->assertSame('selection', $field->getFilterType());
        $this->assertSame(
            ['displayProperty' => 'name', 'resourceKey' => 'product_families'],
            $field->getFilterTypeParameters(),
        );
    }

    public function testTypeIsFilterableByTheTwoProductTypes(): void
    {
        $field = $this->listMetadata()->getField('type');

        $this->assertSame('select', $field->getFilterType());
        $this->assertSame(
            [
                'options' => [
                    'product' => 'sulu_product.product_type_product',
                    'product_with_variants' => 'sulu_product.product_type_product_with_variants',
                ],
            ],
            $field->getFilterTypeParameters(),
        );
    }

    public function testStatusFilterOptionsComeFromTheConfiguredStatuses(): void
    {
        $field = $this->listMetadata()->getField('status');

        $this->assertSame('select', $field->getFilterType());
        $this->assertSame(
            [
                'options' => [
                    'announced' => 'sulu_product.product_status.announced',
                    'available' => 'sulu_product.product_status.available',
                    'discontinued' => 'sulu_product.product_status.discontinued',
                ],
            ],
            $field->getFilterTypeParameters(),
        );
    }

    public function testPublishedStateIsFilterable(): void
    {
        $field = $this->listMetadata()->getField('publishedState');

        $this->assertSame('select', $field->getFilterType());
        $this->assertSame(
            [
                'options' => [
                    'published' => 'sulu_content.state_published',
                    'unpublished' => 'sulu_content.state_not_published',
                    'draft' => 'sulu_admin.draft',
                ],
            ],
            $field->getFilterTypeParameters(),
        );
    }

    public function testForeignListKeyIsIgnored(): void
    {
        // The "attributes" list has no "status" field. If the guard did not return early for a
        // foreign key, ProductsListMetadataVisitor::visitListMetadata() would call
        // $listMetadata->getField('status') below and throw FieldMetadataNotFoundException.
        $field = $this->listMetadata('attributes')->getField('key');

        $this->assertSame('key', $field->getName());
    }

    private function listMetadata(string $key = 'products'): ListMetadata
    {
        self::bootKernel();

        /** @var ListMetadataProvider $provider */
        $provider = self::getContainer()->get('sulu_admin.list_metadata_provider');

        $metadata = $provider->getMetadata($key, 'en');
        $this->assertInstanceOf(ListMetadata::class, $metadata);

        return $metadata;
    }
}
