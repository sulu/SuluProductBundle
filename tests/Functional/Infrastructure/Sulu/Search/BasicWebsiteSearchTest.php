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

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Search;

use CmsIg\Seal\Schema\Schema;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class BasicWebsiteSearchTest extends SuluTestCase
{
    public function testWithoutAdditionalProductFiltersTheIndexHasNoProductField(): void
    {
        self::bootKernel(['environment' => 'test_basic_search']);
        $container = self::getContainer();

        /** @var Schema $schema */
        $schema = $container->get('cmsig_seal.schema.default');

        $this->assertArrayHasKey('website', $schema->indexes);
        $this->assertArrayNotHasKey('product', $schema->indexes['website']->fields);
        $this->assertFalse($container->has('sulu_product.website_product_details_reindex_provider_enhancer'));
        $this->assertFalse($container->has('sulu_product.product_schema_loader'));
    }
}
