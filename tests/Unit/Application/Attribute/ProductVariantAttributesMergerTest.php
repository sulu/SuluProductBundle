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

namespace Sulu\Product\Tests\Unit\Application\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Attribute\ProductVariantAttributesMerger;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(ProductVariantAttributesMerger::class)]
class ProductVariantAttributesMergerTest extends TestCase
{
    public function testTheVariantsValuesAreAddedAndWinOnTheSameKey(): void
    {
        $housing = $this->createValue('housing', 'Zink');
        $staleColour = $this->createValue('colour', 'stale');
        $colour = $this->createValue('colour', 'black');
        $plating = $this->createValue('plating', 'Au');

        $merged = (new ProductVariantAttributesMerger())->merge(
            ['anything' => $housing, 'else' => $staleColour],
            [$colour, $plating],
        );

        $this->assertSame(['housing' => $housing, 'colour' => $colour, 'plating' => $plating], $merged);
    }

    public function testEmptySidesMergeToTheOther(): void
    {
        $housing = $this->createValue('housing', 'Zink');
        $merger = new ProductVariantAttributesMerger();

        $this->assertSame(['housing' => $housing], $merger->merge([$housing], []));
        $this->assertSame(['housing' => $housing], $merger->merge([], [$housing]));
        $this->assertSame([], $merger->merge([], []));
    }

    private function createValue(string $key, string $text): ProductAttributeValue
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), $attribute, $key);
        $value->setText($text);

        return $value;
    }
}
