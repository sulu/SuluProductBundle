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

namespace Sulu\Product\Domain\Association;

final class ProductAssociationTypeRegistry
{
    /** @var array<string, ProductAssociationType> */
    private array $types = [];

    /**
     * @param array<string, array{label: string}> $types
     */
    public function __construct(array $types)
    {
        foreach ($types as $key => $type) {
            $this->types[$key] = new ProductAssociationType($key, $type['label']);
        }
    }

    /**
     * @return ProductAssociationType[]
     */
    public function getTypes(): array
    {
        return \array_values($this->types);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): ProductAssociationType
    {
        return $this->types[$key]
            ?? throw new \InvalidArgumentException(\sprintf('No product association type registered for key "%s".', $key));
    }
}
