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

/**
 * Drops the website index, creates it again from the current schema and reindexes it.
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 */
final class RebuildWebsiteIndexMessage
{
}
