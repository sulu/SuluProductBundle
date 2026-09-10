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

namespace Sulu\Product\Tests\Unit\Application\Search;

use CmsIg\Seal\Adapter\AdapterInterface;
use CmsIg\Seal\Adapter\IndexerInterface;
use CmsIg\Seal\Adapter\Memory\MemoryAdapter;
use CmsIg\Seal\Adapter\SchemaManagerInterface;
use CmsIg\Seal\Adapter\SearcherInterface;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Search\Result;
use CmsIg\Seal\Search\Search;

/**
 * Records the search the searcher builds and answers with an empty result. The memory adapter
 * cannot filter, facet or sort on a nested field, so those cases are asserted on the query.
 */
final class RecordingAdapter implements AdapterInterface, SearcherInterface
{
    public ?Search $search = null;

    private readonly MemoryAdapter $inner;

    public function __construct()
    {
        $this->inner = new MemoryAdapter();
    }

    public function getSchemaManager(): SchemaManagerInterface
    {
        return $this->inner->getSchemaManager();
    }

    public function getIndexer(): IndexerInterface
    {
        return $this->inner->getIndexer();
    }

    public function getSearcher(): SearcherInterface
    {
        return $this;
    }

    public function search(Search $search): Result
    {
        $this->search = $search;

        return new Result((static function(): \Generator {
            yield from [];
        })(), 0);
    }

    public function count(Index $index): int
    {
        return 0;
    }
}
