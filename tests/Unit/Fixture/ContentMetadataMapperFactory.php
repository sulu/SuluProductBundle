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

use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Mcp\Application\Content\ContentMetadataMapper;
use Sulu\Mcp\Application\Metadata\ExtensionFormKeys;

/**
 * Builds the mapper for both constructors: the lowest supported sulu/mcp-bundle takes only the
 * metadata provider, later versions also the seo and excerpt form keys.
 *
 * @internal
 */
final class ContentMetadataMapperFactory
{
    public static function create(MetadataProviderInterface $formMetadataProvider): ContentMetadataMapper
    {
        $arguments = [$formMetadataProvider];

        if (\class_exists(ExtensionFormKeys::class)) {
            $form = ['instanceOf' => \stdClass::class, 'priority' => 0];
            $arguments[] = new ExtensionFormKeys(
                ['content_excerpt_metadata' => $form, 'content_excerpt_taxonomies' => $form],
                ['content_seo_metadata' => $form],
            );
        }

        return (new \ReflectionClass(ContentMetadataMapper::class))->newInstanceArgs($arguments);
    }
}
