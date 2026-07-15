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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Merger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\ProductDetailsMerger;

#[CoversClass(ProductDetailsMerger::class)]
class ProductDetailsMergerTest extends TestCase
{
    use ProphecyTrait;

    private ProductDetailsMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new ProductDetailsMerger();
    }

    private function makeDimensionContent(): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product());
    }

    public function testEarlyReturnWhenTargetNotProductDimensionContent(): void
    {
        $source = $this->makeDimensionContent();
        $source->setTitle('Source');

        $target = new \stdClass();

        $this->merger->merge($target, $source);

        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenSourceNotProductDimensionContent(): void
    {
        $target = $this->makeDimensionContent();
        $source = new \stdClass();

        $this->merger->merge($target, $source);

        $this->assertNull($target->getTitle());
    }

    public function testMergesTitle(): void
    {
        $source = $this->makeDimensionContent();
        $source->setTitle('Merged Title');
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame('Merged Title', $target->getTitle());
    }

    public function testSkipsTitleWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setTitle('Keep This');

        $this->merger->merge($target, $source);

        $this->assertSame('Keep This', $target->getTitle());
    }

    public function testMergesCode(): void
    {
        $source = $this->makeDimensionContent();
        $source->setCode('SKU-MERGED');
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame('SKU-MERGED', $target->getCode());
    }

    public function testSkipsCodeWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setCode('KEEP');

        $this->merger->merge($target, $source);

        $this->assertSame('KEEP', $target->getCode());
    }

    public function testMergesExternalIdentifier(): void
    {
        $source = $this->makeDimensionContent();
        $source->setExternalIdentifier('EXT-X');
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame('EXT-X', $target->getExternalIdentifier());
    }

    public function testSkipsExternalIdentifierWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setExternalIdentifier('EXT-KEEP');

        $this->merger->merge($target, $source);

        $this->assertSame('EXT-KEEP', $target->getExternalIdentifier());
    }

    public function testMergesStatus(): void
    {
        $source = $this->makeDimensionContent();
        $source->setStatus('available');
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame('available', $target->getStatus());
    }

    public function testSkipsStatusWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setStatus('unavailable');

        $this->merger->merge($target, $source);

        $this->assertSame('unavailable', $target->getStatus());
    }

    public function testMergesShortDescription(): void
    {
        $source = $this->makeDimensionContent();
        $source->setShortDescription('<p>hi</p>');
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame('<p>hi</p>', $target->getShortDescription());
    }

    public function testSkipsShortDescriptionWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setShortDescription('<p>keep</p>');

        $this->merger->merge($target, $source);

        $this->assertSame('<p>keep</p>', $target->getShortDescription());
    }

    public function testMergesImage(): void
    {
        $source = $this->makeDimensionContent();
        $source->setImage(5);
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame(5, $target->getImage());
    }

    public function testSkipsImageWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setImage(9);

        $this->merger->merge($target, $source);

        $this->assertSame(9, $target->getImage());
    }

    public function testMergesDocuments(): void
    {
        $source = $this->makeDimensionContent();
        $source->setDocuments([3, 7]);
        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame([3, 7], $target->getDocuments());
    }

    public function testSkipsDocumentsWhenSourceNull(): void
    {
        $source = $this->makeDimensionContent();
        $target = $this->makeDimensionContent();
        $target->setDocuments([1, 2]);

        $this->merger->merge($target, $source);

        $this->assertSame([1, 2], $target->getDocuments());
    }

    public function testMergesProductFamily(): void
    {
        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getTitle()->willReturn(null);
        $source->getCode()->willReturn(null);
        $source->getExternalIdentifier()->willReturn(null);
        $source->getProductFamily()->willReturn($family->reveal());
        $source->getStatus()->willReturn(null);
        $source->getShortDescription()->willReturn(null);
        $source->getImage()->willReturn(null);
        $source->getDocuments()->willReturn(null);

        $target = $this->makeDimensionContent();

        $this->merger->merge($target, $source->reveal());

        $this->assertSame($family->reveal(), $target->getProductFamily());
    }

    public function testSkipsProductFamilyWhenSourceNull(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $source */
        $source = $this->prophesize(ProductDimensionContentInterface::class);
        $source->getTitle()->willReturn(null);
        $source->getCode()->willReturn(null);
        $source->getExternalIdentifier()->willReturn(null);
        $source->getProductFamily()->willReturn(null);
        $source->getStatus()->willReturn(null);
        $source->getShortDescription()->willReturn(null);
        $source->getImage()->willReturn(null);
        $source->getDocuments()->willReturn(null);

        /** @var ObjectProphecy<ProductFamilyInterface> $keepFamily */
        $keepFamily = $this->prophesize(ProductFamilyInterface::class);
        $target = $this->makeDimensionContent();
        $target->setProductFamily($keepFamily->reveal());

        $this->merger->merge($target, $source->reveal());

        $this->assertSame($keepFamily->reveal(), $target->getProductFamily());
    }
}
