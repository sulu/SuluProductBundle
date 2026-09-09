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

namespace Sulu\Product\Infrastructure\Sulu\Search\Visitor;

/**
 * An enhancer that needs data for the whole batch loads it once here instead of per document.
 */
interface BatchAwareReindexEnhancerInterface
{
    /**
     * @param array<int, array<string, mixed>> $rows the query rows of the batch about to be enhanced
     */
    public function prepareBatch(array $rows): void;
}
