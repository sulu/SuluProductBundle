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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductAttributeScope;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductAttributeScope::class)]
class ProductAttributeScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function provideScopes(): iterable
    {
        yield 'product holds shared' => [ProductInterface::TYPE_PRODUCT, false, true];
        yield 'product holds variant' => [ProductInterface::TYPE_PRODUCT, true, true];
        yield 'product with variants holds shared' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, false, true];
        yield 'product with variants leaves variant' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, true, false];
        yield 'variant leaves shared' => [ProductInterface::TYPE_VARIANT, false, false];
        yield 'variant holds variant' => [ProductInterface::TYPE_VARIANT, true, true];
    }

    #[DataProvider('provideScopes')]
    public function testHolds(string $productType, bool $variantSpecific, bool $expected): void
    {
        $familyAttribute = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $familyAttribute->setVariantSpecific($variantSpecific);

        $this->assertSame($expected, ProductAttributeScope::holds($productType, $familyAttribute));
    }
}
