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

namespace Sulu\Product\Infrastructure\Sulu\Search;

use CmsIg\Seal\Adapter\AdapterInterface;
use CmsIg\Seal\Engine;
use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use CmsIg\Seal\Schema\Loader\LoaderInterface;

/**
 * @internal this class is internal no backwards compatibility promise is given for this class
 */
final class RebuildWebsiteIndexMessageHandler
{
    /**
     * @param iterable<ReindexProviderInterface> $reindexProviders
     */
    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly LoaderInterface $schemaLoader,
        private readonly iterable $reindexProviders,
    ) {
    }

    public function __invoke(RebuildWebsiteIndexMessage $message): void
    {
        $engine = new Engine($this->adapter, $this->schemaLoader->load());

        // Todo: This should work without a drop.
        $engine->reindex(
            $this->reindexProviders,
            ReindexConfig::create()
                ->withIndex(WebsiteProductReindexProvider::getIndex())
                ->withDropIndex(true),
        );
    }
}
