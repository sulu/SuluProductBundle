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
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
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
        return new ProductDimensionContent(new Product());
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

    public function testEnhanceEmitsNewFields(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $object */
        $object = $this->prophesize(ProductDimensionContentInterface::class);
        $object->getTitle()->willReturn('T');
        $object->getCode()->willReturn('C');
        $object->getExternalIdentifier()->willReturn(null);
        $object->getProductFamily()->willReturn(null);
        $object->getStatus()->willReturn('available');
        $object->getDetailsData()->willReturn([]);

        $result = $this->normalizer->enhance($object->reveal(), []);

        $this->assertSame('available', $result['status']);
    }

    public function testEnhanceEmitsDetailsBucketUnchanged(): void
    {
        $dc = $this->makeDimensionContent();
        $dc->setDetailsData([
            'shortDescription' => '<p>Hi</p>',
            'image' => ['id' => 5],
            'documents' => ['ids' => [1, 2]],
        ]);

        $result = $this->normalizer->enhance($dc, []);

        // the stored value already carries the admin wire-shape — no reshaping here
        $this->assertSame([
            'shortDescription' => '<p>Hi</p>',
            'image' => ['id' => 5],
            'documents' => ['ids' => [1, 2]],
        ], $result['details']);
    }

    public function testEnhanceEmitsEmptyDetailsBucket(): void
    {
        $dc = $this->makeDimensionContent();

        $result = $this->normalizer->enhance($dc, []);

        $this->assertSame([], $result['details']);
    }

    public function testEnhanceEmitsUnknownProjectFieldUntouched(): void
    {
        $dc = $this->makeDimensionContent();
        $dc->setDetailsData(['datasheet' => ['id' => 9]]);

        $result = $this->normalizer->enhance($dc, []);

        $this->assertSame(['datasheet' => ['id' => 9]], $result['details']);
    }
}
