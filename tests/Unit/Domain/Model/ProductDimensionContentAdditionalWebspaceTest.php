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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentAdditionalWebspace;
use Sulu\Product\Domain\Model\ProductFamily;

#[CoversClass(ProductDimensionContentAdditionalWebspace::class)]
class ProductDimensionContentAdditionalWebspaceTest extends TestCase
{
    public function testConstructorAssignsValues(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));
        $additionalWebspace = new ProductDimensionContentAdditionalWebspace('sulu-io', $dimensionContent);

        $this->assertSame('sulu-io', $additionalWebspace->getAdditionalWebspace());
        $this->assertSame($dimensionContent, $additionalWebspace->getProductDimensionContent());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new ProductDimensionContentAdditionalWebspace('sulu-io', new ProductDimensionContent(new Product(new ProductFamily())));
        $ref = new \ReflectionProperty(ProductDimensionContentAdditionalWebspace::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
