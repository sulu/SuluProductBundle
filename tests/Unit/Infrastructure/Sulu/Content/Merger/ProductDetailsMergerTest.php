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

    public function testMergesDetailsBucketPerKeyLocalizedWins(): void
    {
        $target = $this->makeDimensionContent();
        $target->setDetailsData(['image' => ['id' => 5], 'shortDescription' => 'old']);

        $source = $this->makeDimensionContent();
        $source->setDetailsData(['shortDescription' => 'new']);

        $this->merger->merge($target, $source);

        // unlocalized merges first, localized second — so the localized half wins per key
        // while the unlocalized half it does not carry survives untouched
        $this->assertSame(
            ['image' => ['id' => 5], 'shortDescription' => 'new'],
            $target->getDetailsData(),
        );
    }

    public function testMergesDetailsHalvesIntoOneBucket(): void
    {
        // the mapper splits the bucket per multilingual; the merger reunites the halves
        $target = $this->makeDimensionContent();
        $target->setDetailsData(['image' => ['id' => 5], 'documents' => ['ids' => [1, 2]]]);

        $source = $this->makeDimensionContent();
        $source->setDetailsData(['shortDescription' => '<p>hi</p>']);

        $this->merger->merge($target, $source);

        $this->assertSame(
            ['image' => ['id' => 5], 'documents' => ['ids' => [1, 2]], 'shortDescription' => '<p>hi</p>'],
            $target->getDetailsData(),
        );
    }

    public function testKeepsTargetDetailsWhenSourceBucketEmpty(): void
    {
        $target = $this->makeDimensionContent();
        $target->setDetailsData(['shortDescription' => '<p>keep</p>']);

        $source = $this->makeDimensionContent();

        $this->merger->merge($target, $source);

        $this->assertSame(['shortDescription' => '<p>keep</p>'], $target->getDetailsData());
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
        $source->getDetailsData()->willReturn([]);

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
        $source->getDetailsData()->willReturn([]);

        /** @var ObjectProphecy<ProductFamilyInterface> $keepFamily */
        $keepFamily = $this->prophesize(ProductFamilyInterface::class);
        $target = $this->makeDimensionContent();
        $target->setProductFamily($keepFamily->reveal());

        $this->merger->merge($target, $source->reveal());

        $this->assertSame($keepFamily->reveal(), $target->getProductFamily());
    }
}
