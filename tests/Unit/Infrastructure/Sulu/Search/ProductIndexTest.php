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

    public function testAttributeFieldSanitisesKey(): void
    {
        $this->assertSame('attr_cable_length', ProductIndex::attributeField('cable_length'));
        $this->assertSame('attr_cable_length_mm', ProductIndex::attributeField('cable-length.mm'));
    }

    public function testOptionField(): void
    {
        $this->assertSame('opt_colour', ProductIndex::optionField('colour'));
    }
}
