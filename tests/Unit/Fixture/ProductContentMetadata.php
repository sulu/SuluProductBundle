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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;

/**
 * Metadata that makes the content, excerpt and SEO validation of the product tools reject unknown input:
 * a "default" product template with a "blocks" list of "text" blocks that only know a "title".
 *
 * @internal
 */
final class ProductContentMetadata
{
    public static function provider(): ArrayMetadataProvider
    {
        $textBlock = new FormMetadata();
        $textBlock->setKey('text');
        $textBlock->addItem(self::field('title'));

        $blocks = self::field('blocks');
        $blocks->addType($textBlock);

        $template = new FormMetadata();
        $template->setKey('default');
        $template->addItem($blocks);

        $typed = new TypedFormMetadata();
        $typed->addForm('default', $template);

        $provider = new ArrayMetadataProvider();
        $provider->set('product', $typed);
        $provider->set('content_excerpt_metadata', self::form('excerpt/title'));
        $provider->set('content_excerpt_taxonomies', self::form('excerptTags'));
        $provider->set('content_seo_metadata', self::form('seo/title'));
        $provider->setDefault(new FormMetadata());

        return $provider;
    }

    private static function form(string $fieldName): FormMetadata
    {
        $form = new FormMetadata();
        $form->addItem(self::field($fieldName));

        return $form;
    }

    private static function field(string $name): FieldMetadata
    {
        return new FieldMetadata($name);
    }
}
