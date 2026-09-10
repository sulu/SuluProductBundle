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

return new Index('website', [
    'product' => new Field\ObjectField('product', [
        'code' => new Field\TextField('code', searchable: false, filterable: true),
        // TODO rename to `productFamilyKey` and write the family key instead of the uuid.
        'productFamilyId' => new Field\TextField('productFamilyId', searchable: false, filterable: true, facet: true),
        'attributes_text_values' => new Field\TextField('attributes_text_values', multiple: true, searchable: false, filterable: true, facet: true),
        'attributes_numeric_values' => new Field\ObjectField('attributes_numeric_values', []),
    ]),
]);
