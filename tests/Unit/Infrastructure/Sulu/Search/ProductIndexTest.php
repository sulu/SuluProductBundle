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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

#[CoversClass(ProductIndex::class)]
class ProductIndexTest extends TestCase
{
    public function testDocumentId(): void
    {
        $this->assertSame('products__abc__de', ProductIndex::documentId('abc', 'de'));
    }

    public function testTextValuesPath(): void
    {
        $this->assertSame('product.attributes_text_values', ProductIndex::textValuesPath());
    }

    public function testTextValueJoinsKeyAndValue(): void
    {
        $this->assertSame('colour:black', ProductIndex::textValue('colour', 'black'));
        $this->assertSame('cable_length:5 m', ProductIndex::textValue('cable-length', '5 m'));
    }

    public function testNumericFieldSanitisesKey(): void
    {
        $this->assertSame('cable_length', ProductIndex::numericField('cable_length'));
        $this->assertSame('cable_length_mm', ProductIndex::numericField('cable-length.mm'));
    }

    public function testNumericValuePath(): void
    {
        $this->assertSame('product.attributes_numeric_values.weight', ProductIndex::numericValuePath('weight'));
    }
}
