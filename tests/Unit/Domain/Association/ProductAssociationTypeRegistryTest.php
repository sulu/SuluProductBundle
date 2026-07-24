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

namespace Sulu\Product\Tests\Unit\Domain\Association;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Association\ProductAssociationType;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;

#[CoversClass(ProductAssociationTypeRegistry::class)]
#[CoversClass(ProductAssociationType::class)]
final class ProductAssociationTypeRegistryTest extends TestCase
{
    public function testGetTypesReturnsValueObjects(): void
    {
        $registry = new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
        ]);

        $types = $registry->getTypes();

        self::assertSame('alternative', $types[0]->getKey());
        self::assertSame('sulu_product.association_type_alternative', $types[0]->getLabel());
    }

    public function testHasReturnsTrueForKnownKey(): void
    {
        $registry = new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
        ]);

        self::assertTrue($registry->has('alternative'));
    }

    public function testHasReturnsFalseForUnknownKey(): void
    {
        $registry = new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
        ]);

        self::assertFalse($registry->has('unknown'));
    }

    public function testGetReturnsType(): void
    {
        $registry = new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
        ]);

        $type = $registry->get('alternative');

        self::assertSame('alternative', $type->getKey());
        self::assertSame('sulu_product.association_type_alternative', $type->getLabel());
    }

    public function testGetThrowsForUnknownKey(): void
    {
        $registry = new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'sulu_product.association_type_alternative'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $registry->get('unknown');
    }

    public function testEmptyConfigYieldsNoTypes(): void
    {
        $registry = new ProductAssociationTypeRegistry([]);

        self::assertSame([], $registry->getTypes());
    }
}
