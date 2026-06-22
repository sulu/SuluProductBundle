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

namespace Sulu\Product\Application\AttributeType;

final class AttributeTypeRegistry
{
    /** @var array<string, AttributeTypeInterface> */
    private array $types = [];

    /**
     * @param iterable<AttributeTypeInterface> $types
     */
    public function __construct(iterable $types)
    {
        foreach ($types as $type) {
            $this->types[$type->getKey()] = $type;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): AttributeTypeInterface
    {
        return $this->types[$key]
            ?? throw new \InvalidArgumentException(\sprintf('No attribute type registered for key "%s".', $key));
    }
}
