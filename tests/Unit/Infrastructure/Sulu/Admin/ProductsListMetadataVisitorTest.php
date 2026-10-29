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
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadata;
use Sulu\Product\Application\Routing\PerProductSlugProvider;
use Sulu\Product\Application\Routing\PerVariantSlugProvider;
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductsListMetadataVisitor;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;

#[CoversClass(ProductsListMetadataVisitor::class)]
class ProductsListMetadataVisitorTest extends TestCase
{
    public function testInjectsConfiguredStatusOptions(): void
    {
        $statusField = new FieldMetadata('status');

        $listMetadata = new ListMetadata();
        $listMetadata->addField($statusField);
        $listMetadata->addField($this->createTypeField());

        $visitor = new ProductsListMetadataVisitor(['announced', 'available'], new PerVariantSlugProvider());
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY, 'en');

        $this->assertSame(
            ['options' => [
                'announced' => 'sulu_product.product_status.announced',
                'available' => 'sulu_product.product_status.available',
            ]],
            $statusField->getFilterTypeParameters(),
        );
    }

    public function testASelectionOffersNoProductWithVariants(): void
    {
        $typeField = $this->createTypeField();
        $listMetadata = new ListMetadata();
        $listMetadata->addField(new FieldMetadata('status'));
        $listMetadata->addField($typeField);

        $visitor = new ProductsListMetadataVisitor([], new PerVariantSlugProvider());
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY, 'en');

        $this->assertSame(
            ['options' => [ProductInterface::TYPE_PRODUCT => 'product', ProductInterface::TYPE_VARIANT => 'variant']],
            $typeField->getFilterTypeParameters(),
        );
    }

    public function testASelectionOffersNoVariantInPerProductRouting(): void
    {
        $typeField = $this->createTypeField();
        $listMetadata = new ListMetadata();
        $listMetadata->addField(new FieldMetadata('status'));
        $listMetadata->addField($typeField);

        $visitor = new ProductsListMetadataVisitor([], new PerProductSlugProvider($this->createStub(RouteRepositoryInterface::class), new VariantUrlBuilder('variant')));
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY, 'en');

        $this->assertSame(
            ['options' => [ProductInterface::TYPE_PRODUCT => 'product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS => 'product_with_variants']],
            $typeField->getFilterTypeParameters(),
        );
    }

    public function testTheAdminListOffersNoVariant(): void
    {
        $typeField = $this->createTypeField();
        $listMetadata = new ListMetadata();
        $listMetadata->addField(new FieldMetadata('status'));
        $listMetadata->addField($typeField);

        $visitor = new ProductsListMetadataVisitor([], new PerVariantSlugProvider());
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY, 'en', ['excludeVariants' => 'true']);

        $this->assertSame(
            ['options' => [ProductInterface::TYPE_PRODUCT => 'product', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS => 'product_with_variants']],
            $typeField->getFilterTypeParameters(),
        );
    }

    public function testLeavesOtherListsAlone(): void
    {
        $statusField = new FieldMetadata('status');

        $listMetadata = new ListMetadata();
        $listMetadata->addField($statusField);

        $visitor = new ProductsListMetadataVisitor(['announced', 'available'], new PerVariantSlugProvider());
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY_VARIANTS, 'en');

        $this->assertNull($statusField->getFilterTypeParameters());
    }

    private function createTypeField(): FieldMetadata
    {
        $typeField = new FieldMetadata('type');
        $typeField->setFilterTypeParameters(['options' => [
            ProductInterface::TYPE_PRODUCT => 'product',
            ProductInterface::TYPE_PRODUCT_WITH_VARIANTS => 'product_with_variants',
            ProductInterface::TYPE_VARIANT => 'variant',
        ]]);

        return $typeField;
    }
}
