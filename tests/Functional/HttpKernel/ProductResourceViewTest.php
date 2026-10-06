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
use Sulu\Bundle\AdminBundle\Admin\AdminPool;
use Sulu\Bundle\AdminBundle\Admin\View\ResourceViewUrlGenerator;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\AdminBundle\Admin\View\ViewUrlGenerator;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;

/**
 * The notifier links a notification through the "detail" view of the resource. Without it the
 * notification is sent without a link and without a log line.
 */
#[CoversClass(SuluProductBundle::class)]
class ProductResourceViewTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testProductResourceRegistersTheEditViewAsDetailView(): void
    {
        self::bootKernel();

        /** @var array<string, array{views?: array<string, string>}> $resources */
        $resources = self::getContainer()->getParameter('sulu_admin.resources');

        $this->assertSame(
            ProductAdmin::EDIT_TABS_VIEW,
            $resources[ProductInterface::RESOURCE_KEY]['views']['detail'] ?? null,
        );
    }

    public function testProductDetailViewResolvesToTheEditUrl(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        // the admin services are removed from the compiled test container, so the generator is
        // assembled from the same parts the admin context wires: the admin views and resources
        /** @var AdminPool $adminPool */
        $adminPool = $container->get('sulu_admin.admin_pool');
        /** @var array<string, array{views?: array<string, string>}> $resources */
        $resources = $container->getParameter('sulu_admin.resources');

        $generator = new ResourceViewUrlGenerator(
            new ViewUrlGenerator($container->get('router'), new ViewRegistry($adminPool), $container->get('request_stack')),
            $resources,
        );

        // the notifier also passes the webspace, which the product edit route does not use
        $url = $generator->generate(
            ProductInterface::RESOURCE_KEY,
            'detail',
            ['id' => 'a-product-uuid', 'locale' => 'de', 'webspace' => 'example'],
        );

        $this->assertSame('/admin/#/de/products/a-product-uuid', $url);
    }
}
