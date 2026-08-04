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
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\Normalizer\ProductAssociationsNormalizer;

#[CoversClass(ProductAssociationsNormalizer::class)]
final class ProductAssociationsNormalizerTest extends TestCase
{
    use ProphecyTrait;

    private ProductAssociationsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProductAssociationsNormalizer(
            new ProductAssociationTypeRegistry([
                'alternative' => ['label' => 'Alternative'],
                'suitable' => ['label' => 'Suitable'],
            ]),
        );
    }

    public function testGetIgnoredAttributesForNonProductDimensionContent(): void
    {
        $result = $this->normalizer->getIgnoredAttributes(new \stdClass());

        self::assertSame([], $result);
    }

    public function testIgnoredAttributesContainsAssociations(): void
    {
        $dc = new ProductDimensionContent(new Product());

        $result = $this->normalizer->getIgnoredAttributes($dc);

        self::assertSame(['associations'], $result);
    }

    public function testEnhanceForNonProductDimensionContent(): void
    {
        $data = ['foo' => 'bar'];

        $result = $this->normalizer->enhance(new \stdClass(), $data);

        self::assertSame($data, $result);
    }

    public function testRetainsDigitOnlyUnregisteredTypeWithoutFatalError(): void
    {
        $dc = new ProductDimensionContent(new Product('uuid-source'));
        $dc->addAssociation(new ProductAssociation($dc, new Product('uuid-b'), '123', 0));

        $result = $this->normalizer->enhance($dc, []);

        self::assertSame(['alternative' => [], 'suitable' => [], '123' => ['uuid-b']], $result['associations']);
    }

    public function testSeedsEveryConfiguredTypeToEmptyArray(): void
    {
        $dc = new ProductDimensionContent(new Product());

        $result = $this->normalizer->enhance($dc, []);

        self::assertSame(['alternative' => [], 'suitable' => []], $result['associations']);
    }

    public function testOverlaysStoredRowsPositionSorted(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $productB = new Product('uuid-b');
        $productC = new Product('uuid-c');

        $dc->addAssociation(new ProductAssociation($dc, $productC, 'alternative', 1));
        $dc->addAssociation(new ProductAssociation($dc, $productB, 'alternative', 0));

        $result = $this->normalizer->enhance($dc, []);

        $associations = $result['associations'];
        self::assertIsArray($associations);
        self::assertSame(['uuid-b', 'uuid-c'], $associations['alternative']);
        self::assertSame([], $associations['suitable']);
    }

    public function testEmitsRetainedRemovedTypeRows(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $productLegacy = new Product('uuid-legacy');

        $dc->addAssociation(new ProductAssociation($dc, $productLegacy, 'legacy', 0));

        $result = $this->normalizer->enhance($dc, []);

        $associations = $result['associations'];
        self::assertIsArray($associations);
        self::assertSame(['uuid-legacy'], $associations['legacy']);
        self::assertSame(['alternative' => [], 'suitable' => [], 'legacy' => ['uuid-legacy']], $associations);
    }
}
