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

use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\SeoInterface;
use Sulu\Content\Domain\Model\TaxonomyInterface;
use Sulu\Mcp\Application\Metadata\ExtensionFormKeys;

/**
 * The seo / excerpt form registry of a Sulu install without project forms.
 */
final class CoreExtensionFormKeys
{
    public static function create(): ExtensionFormKeys
    {
        return new ExtensionFormKeys(
            [
                'content_excerpt_metadata' => ['instanceOf' => ExcerptInterface::class, 'priority' => 100],
                'content_excerpt_taxonomies' => ['instanceOf' => TaxonomyInterface::class, 'priority' => 50],
            ],
            ['content_seo_metadata' => ['instanceOf' => SeoInterface::class, 'priority' => 100]],
        );
    }
}
