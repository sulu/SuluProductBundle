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

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

// Static part of the catalogue index. Per-attribute fields are appended at
// runtime by ProductSchemaLoader.
return new Index(ProductIndex::NAME, [
    'id' => new Field\IdentifierField('id'),
    'resourceId' => new Field\TextField('resourceId', searchable: false, filterable: true),
    'type' => new Field\TextField('type', searchable: false, filterable: true),
    'parentId' => new Field\TextField('parentId', searchable: false, filterable: true),
    'code' => new Field\TextField('code', filterable: true),
    'locale' => new Field\TextField('locale', searchable: false, filterable: true),
    'webspaces' => new Field\TextField('webspaces', multiple: true, searchable: false, filterable: true),
    'title' => new Field\TextField('title'),
    'url' => new Field\TextField('url', searchable: false),
    'content' => new Field\TextField('content', multiple: true),
    'mediaId' => new Field\IntegerField('mediaId'),
    'productFamilyId' => new Field\TextField('productFamilyId', searchable: false, filterable: true, facet: true),
    'productFamilyName' => new Field\TextField('productFamilyName', searchable: false),
    'status' => new Field\TextField('status', searchable: false, filterable: true, facet: true),
    'authoredAt' => new Field\DateTimeField('authoredAt', sortable: true),
    'changedAt' => new Field\DateTimeField('changedAt', sortable: true),
    'attributes' => new Field\JsonObjectField('attributes'),
    'metadata' => new Field\JsonObjectField('metadata'),
]);
