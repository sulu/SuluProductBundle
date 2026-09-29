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

namespace Sulu\Product\Tests\Unit\Fixture;

use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;

/**
 * Hands out predictable ids. Sulu's real generator produces random UUIDs, which
 * assertions on written block data cannot pin down.
 *
 * @internal
 */
final class FixedBlockIdGenerator implements BlockIdGeneratorInterface
{
    private int $calls = 0;

    /** @var list<string> */
    private array $queue = [];

    public function __construct(
        private readonly string $id = 'gen-id',
    ) {
    }

    /**
     * Hands out the given ids in order, for tests that assert on each block's id.
     */
    public static function returning(string ...$ids): self
    {
        $generator = new self();
        $generator->queue = \array_values($ids);

        return $generator;
    }

    public function generateId(): string
    {
        ++$this->calls;

        if ([] !== $this->queue) {
            return \array_shift($this->queue);
        }

        return $this->id;
    }

    public function calls(): int
    {
        return $this->calls;
    }
}
