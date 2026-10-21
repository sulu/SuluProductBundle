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
    /**
     * Reserved key: holds the components of a configurable product and is edited on its own tab.
     */
    public const COMPONENT = 'component';

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

    /**
     * The types edited on the associations tab, that is all but the component type.
     *
     * @return ProductAssociationType[]
     */
    public function getCustomTypes(): array
    {
        return \array_values(\array_filter(
            $this->types,
            static fn (ProductAssociationType $type): bool => self::COMPONENT !== $type->getKey(),
        ));
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
