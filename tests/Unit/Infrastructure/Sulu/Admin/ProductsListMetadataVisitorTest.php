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
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductsListMetadataVisitor;

#[CoversClass(ProductsListMetadataVisitor::class)]
class ProductsListMetadataVisitorTest extends TestCase
{
    public function testInjectsConfiguredStatusOptions(): void
    {
        $statusField = new FieldMetadata('status');

        $listMetadata = new ListMetadata();
        $listMetadata->addField($statusField);

        $visitor = new ProductsListMetadataVisitor(['announced', 'available']);
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY, 'en');

        $this->assertSame(
            ['options' => [
                'announced' => 'sulu_product.product_status.announced',
                'available' => 'sulu_product.product_status.available',
            ]],
            $statusField->getFilterTypeParameters(),
        );
    }

    public function testLeavesOtherListsAlone(): void
    {
        $statusField = new FieldMetadata('status');

        $listMetadata = new ListMetadata();
        $listMetadata->addField($statusField);

        $visitor = new ProductsListMetadataVisitor(['announced', 'available']);
        $visitor->visitListMetadata($listMetadata, ProductInterface::LIST_KEY_VARIANTS, 'en');

        $this->assertNull($statusField->getFilterTypeParameters());
    }
}
