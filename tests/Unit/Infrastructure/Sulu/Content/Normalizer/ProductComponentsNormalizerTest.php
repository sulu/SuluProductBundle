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
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\Normalizer\ProductComponentsNormalizer;

#[CoversClass(ProductComponentsNormalizer::class)]
final class ProductComponentsNormalizerTest extends TestCase
{
    private ProductComponentsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProductComponentsNormalizer();
    }

    public function testGetIgnoredAttributesForNonProductDimensionContent(): void
    {
        self::assertSame([], $this->normalizer->getIgnoredAttributes(new \stdClass()));
    }

    public function testIgnoredAttributesContainsComponents(): void
    {
        self::assertSame(['components'], $this->normalizer->getIgnoredAttributes(new ProductDimensionContent(new Product())));
    }

    public function testEnhanceForNonProductDimensionContent(): void
    {
        $data = ['foo' => 'bar'];

        self::assertSame($data, $this->normalizer->enhance(new \stdClass(), $data));
    }

    public function testEnhanceWithoutComponentsAddsEmptyList(): void
    {
        $result = $this->normalizer->enhance(new ProductDimensionContent(new Product()), ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar', 'components' => []], $result);
    }

    public function testEnhanceListsComponentUuidsInPositionOrder(): void
    {
        $dc = new ProductDimensionContent(new Product('uuid-source'));
        $dc->addComponent(new ProductComponent($dc, new Product('uuid-c'), 1));
        $dc->addComponent(new ProductComponent($dc, new Product('uuid-b'), 0));

        $result = $this->normalizer->enhance($dc, []);

        self::assertSame(['uuid-b', 'uuid-c'], $result['components']);
    }
}
