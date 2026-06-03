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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\FormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ListViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ResourceTabViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

#[CoversClass(ProductAdmin::class)]
class ProductAdminTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ViewBuilderFactoryInterface> */
    private ObjectProphecy $viewBuilderFactory;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<LocalizationManagerInterface> */
    private ObjectProphecy $localizationManager;

    /** @var ObjectProphecy<ActivityViewBuilderFactoryInterface> */
    private ObjectProphecy $activityViewBuilderFactory;

    private ProductAdmin $admin;

    protected function setUp(): void
    {
        $this->viewBuilderFactory = $this->prophesize(ViewBuilderFactoryInterface::class);
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->localizationManager = $this->prophesize(LocalizationManagerInterface::class);
        $this->localizationManager->getLocales()->willReturn(['en', 'de']);
        $this->activityViewBuilderFactory = $this->prophesize(ActivityViewBuilderFactoryInterface::class);
        $this->activityViewBuilderFactory->hasActivityListPermission()->willReturn(false);

        $this->admin = new ProductAdmin(
            $this->viewBuilderFactory->reveal(),
            $this->securityChecker->reveal(),
            $this->localizationManager->reveal(),
            $this->activityViewBuilderFactory->reveal(),
        );
    }

    public function testConfigureNavigationItemsEmpty(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $this->assertSame([], $collection->all());
    }

    public function testConfigureNavigationItems(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $item = \reset($items);
        $this->assertSame(ProductAdmin::LIST_VIEW, $item->getView());
        $this->assertEmpty($item->getChildren());
    }

    public function testGetSecurityContexts(): void
    {
        $contexts = $this->admin->getSecurityContexts();

        $this->assertSame([
            PermissionTypes::VIEW,
            PermissionTypes::ADD,
            PermissionTypes::EDIT,
            PermissionTypes::DELETE,
            PermissionTypes::LIVE,
        ], $contexts['Sulu']['Product'][ProductAdmin::SECURITY_CONTEXT]);
    }

    public function testConfigureViewsWithoutEditPermission(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(0, $viewCollection->all());
    }

    public function testConfigureViewsWithFullPermissions(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(true);

        $this->prepareFluentBuilders();

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertGreaterThan(0, \count($viewCollection->all()));
    }

    public function testConfigureViewsWithActivityInsightsView(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)->willReturn(false);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)->willReturn(false);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(false);

        $this->activityViewBuilderFactory->hasActivityListPermission()->willReturn(true);

        $this->prepareFluentBuilders();

        // Pre-populate ViewCollection with the insights tab view so the condition is true
        $insightsViewName = ProductAdmin::EDIT_TABS_VIEW . '.insights';
        $insightsBuilder = $this->prophesize(ResourceTabViewBuilderInterface::class);
        $insightsBuilder->getName()->willReturn($insightsViewName);

        $activityListBuilder = $this->prophesize(ListViewBuilderInterface::class);
        $activityListBuilder->setParent(Argument::any())->willReturn($activityListBuilder->reveal());
        $activityListBuilder->getName()->willReturn($insightsViewName . '.activity');

        $this->activityViewBuilderFactory->createActivityListViewBuilder(Argument::cetera())->willReturn($activityListBuilder->reveal());

        $viewCollection = new ViewCollection();
        $viewCollection->add($insightsBuilder->reveal());

        $this->admin->configureViews($viewCollection);

        $this->assertTrue($viewCollection->has($insightsViewName . '.activity'));
    }

    private function prepareFluentBuilders(): void
    {
        $listBuilder = $this->prophesize(ListViewBuilderInterface::class);
        foreach ([
            'setResourceKey', 'setListKey', 'addListAdapters', 'addLocales',
            'setDefaultLocale', 'setAddView', 'setEditView', 'addToolbarActions',
        ] as $method) {
            $listBuilder->$method(Argument::any())->willReturn($listBuilder->reveal());
        }
        $listBuilder->getName()->willReturn(ProductAdmin::LIST_VIEW);

        $resourceTabBuilder = $this->prophesize(ResourceTabViewBuilderInterface::class);
        foreach ([
            'setResourceKey', 'addLocales', 'setBackView', 'setTitleProperty',
        ] as $method) {
            $resourceTabBuilder->$method(Argument::any())->willReturn($resourceTabBuilder->reveal());
        }
        $resourceTabBuilder->getName()->willReturn('tab');

        $formBuilder = $this->prophesize(FormViewBuilderInterface::class);
        foreach ([
            'setResourceKey', 'setFormKey', 'setTabTitle', 'setTabOrder',
            'addToolbarActions', 'setEditView', 'setParent',
        ] as $method) {
            $formBuilder->$method(Argument::any())->willReturn($formBuilder->reveal());
        }
        $formBuilder->getName()->willReturn('form');

        $this->viewBuilderFactory->createListViewBuilder(Argument::cetera())->willReturn($listBuilder->reveal());
        $this->viewBuilderFactory->createResourceTabViewBuilder(Argument::cetera())->willReturn($resourceTabBuilder->reveal());
        $this->viewBuilderFactory->createFormViewBuilder(Argument::cetera())->willReturn($formBuilder->reveal());
    }
}
