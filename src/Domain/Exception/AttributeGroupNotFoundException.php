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

namespace Sulu\Product\Domain\Exception;

class AttributeGroupNotFoundException extends \Exception
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function __construct(private array $criteria, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('AttributeGroup with criteria (%s) not found.', \json_encode($this->criteria)),
            0,
            $previous
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }
}
