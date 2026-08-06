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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\AdminBundle\Admin\View\View;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadata;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductContentAdmin;

/**
 * The content bundle derives the versions keys from the dimension content resource key, so it only
 * finds the registered "products_versions" list while that key stays aligned with the product one.
 */
#[CoversClass(ProductContentAdmin::class)]
class ProductVersionsViewTest extends SuluTestCase
{
    private const VERSIONS_VIEW = ProductAdmin::EDIT_TABS_VIEW . '.insights.versions';

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testVersionsListKeyResolvesToListMetadata(): void
    {
        self::bootKernel();

        $listKey = $this->getVersionsView()->getOption('listKey');
        $this->assertSame('products_versions', $listKey);

        /** @var ListMetadataProvider $listMetadataProvider */
        $listMetadataProvider = self::getContainer()->get('sulu_admin.list_metadata_provider');

        // the admin requests /admin/metadata/list/<listKey>, which answers 404 without metadata
        $this->assertInstanceOf(ListMetadata::class, $listMetadataProvider->getMetadata($listKey, 'en'));
    }

    public function testVersionsResourceKeyIsRegisteredWithAListRoute(): void
    {
        self::bootKernel();

        $resourceKey = $this->getVersionsView()->getOption('resourceKey');
        $this->assertSame('products_versions', $resourceKey);

        /** @var array<string, array{routes: array<string, string>}> $resources */
        $resources = self::getContainer()->getParameter('sulu_admin.resources');

        $this->assertArrayHasKey($resourceKey, $resources);
        $this->assertArrayHasKey('list', $resources[$resourceKey]['routes']);
    }

    private function getVersionsView(): View
    {
        /** @var ProductContentAdmin $admin */
        $admin = self::getContainer()->get('sulu_product.product_content_admin');

        $viewCollection = new ViewCollection();
        $admin->configureViews($viewCollection);

        return $viewCollection->get(self::VERSIONS_VIEW)->getView();
    }
}
