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

namespace Sulu\Product\Domain\Measurement;

final class Unit
{
    public function __construct(
        private readonly string $key,
        private readonly string $symbol,
        private readonly MeasurementFamily $family,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getMeasurementFamily(): MeasurementFamily
    {
        return $this->family;
    }
}
