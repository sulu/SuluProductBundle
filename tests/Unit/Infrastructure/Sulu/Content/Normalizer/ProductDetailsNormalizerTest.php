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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Normalizer\ProductDetailsNormalizer;

#[CoversClass(ProductDetailsNormalizer::class)]
class ProductDetailsNormalizerTest extends TestCase
{
    use ProphecyTrait;

    private ProductDetailsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProductDetailsNormalizer();
    }

    private function makeDimensionContent(): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product(new ProductFamily()));
    }

    public function testGetIgnoredAttributesForNonProductDimensionContent(): void
    {
        $result = $this->normalizer->getIgnoredAttributes(new \stdClass());

        $this->assertSame([], $result);
    }

    public function testGetIgnoredAttributesForProductDimensionContent(): void
    {
        $dc = $this->makeDimensionContent();

        $result = $this->normalizer->getIgnoredAttributes($dc);

        $this->assertSame(['productFamily'], $result);
    }

    public function testEnhanceForNonProductDimensionContent(): void
    {
        $data = ['foo' => 'bar'];

        $result = $this->normalizer->enhance(new \stdClass(), $data);

        $this->assertSame($data, $result);
    }

    public function testEnhanceAddsProductDetails(): void
    {
        $dc = $this->makeDimensionContent();
        $dc->setTitle('My Product');
        $dc->setCode('SKU-001');
        $dc->setExternalIdentifier('EXT-99');

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getUuid()->willReturn('fam-uuid');
        $dc->setProductFamily($family->reveal());

        $result = $this->normalizer->enhance($dc, []);

        $this->assertSame('My Product', $result['title']);
        $this->assertSame('SKU-001', $result['code']);
        $this->assertSame('EXT-99', $result['externalIdentifier']);
        $this->assertSame('fam-uuid', $result['productFamily']);
    }

    public function testEnhanceWithNullProductFamily(): void
    {
        $dc = $this->makeDimensionContent();

        $result = $this->normalizer->enhance($dc, []);

        $this->assertNull($result['productFamily']);
    }
}
