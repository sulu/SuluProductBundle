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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\ActivityAdmin;
use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

#[CoversClass(ProductAdmin::class)]
class ProductAdminTest extends TestCase
{
    use ProphecyTrait;

    private ViewBuilderFactory $viewBuilderFactory;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<LocalizationManagerInterface> */
    private ObjectProphecy $localizationManager;

    private ActivityViewBuilderFactory $activityViewBuilderFactory;

    private ProductAdmin $admin;

    protected function setUp(): void
    {
        $this->viewBuilderFactory = new ViewBuilderFactory();
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->localizationManager = $this->prophesize(LocalizationManagerInterface::class);
        $this->localizationManager->getLocales()->willReturn(['en', 'de']);
        $this->activityViewBuilderFactory = new ActivityViewBuilderFactory(
            $this->viewBuilderFactory,
            $this->securityChecker->reveal(),
        );

        $this->admin = new ProductAdmin(
            $this->viewBuilderFactory,
            $this->securityChecker->reveal(),
            $this->localizationManager->reveal(),
            $this->activityViewBuilderFactory,
        );
    }

    public function testConfigureNavigationItemsEmpty(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $this->assertSame([], $collection->all());
    }

    public function testConfigureNavigationItemsBothChildren(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $parent = \reset($items);
        $this->assertCount(2, $parent->getChildren());
    }

    public function testConfigureNavigationItemsOnlyProducts(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $parent = \reset($items);
        $this->assertCount(1, $parent->getChildren());
    }

    public function testConfigureNavigationItemsAddsAttributeGroupsChild(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $parent = \reset($items);
        $children = $parent->getChildren();
        $this->assertCount(1, $children);
        $child = \reset($children);
        $this->assertSame(AttributeGroupAdmin::LIST_VIEW, $child->getView());
    }

    public function testConfigureNavigationItemsAllThreeChildren(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $parent = \reset($items);
        $this->assertCount(3, $parent->getChildren());
    }

    public function testConfigureNavigationItemsAddsProductFamiliesChildAfterAttributeGroups(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $parent = \reset($items);
        $children = $parent->getChildren();
        $this->assertCount(2, $children);
        $this->assertSame(AttributeGroupAdmin::LIST_VIEW, $children[0]->getView());
        $this->assertSame(ProductFamilyAdmin::LIST_VIEW, $children[1]->getView());
    }

    public function testConfigureNavigationItemsAllFourChildren(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);

        $collection = new NavigationItemCollection();
        $this->admin->configureNavigationItems($collection);

        $items = $collection->all();
        $this->assertCount(1, $items);
        $parent = \reset($items);
        $this->assertCount(4, $parent->getChildren());
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
        $this->securityChecker->hasPermission(ActivityAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(6, $viewCollection->all());
        $this->assertTrue($viewCollection->has(ProductAdmin::LIST_VIEW));
        $this->assertTrue($viewCollection->has(ProductAdmin::ADD_TABS_VIEW));
        $this->assertTrue($viewCollection->has(ProductAdmin::EDIT_TABS_VIEW));
        $this->assertTrue($viewCollection->has(ProductAdmin::ADD_TABS_VIEW . '.details'));
        $this->assertTrue($viewCollection->has(ProductAdmin::EDIT_TABS_VIEW . '.details'));
    }

    public function testConfigureViewsRegistersAttributesTabForEditOnly(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(true);
        $this->securityChecker->hasPermission(ActivityAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        self::assertTrue($viewCollection->has(ProductAdmin::EDIT_TABS_VIEW . '.attributes'));
        self::assertFalse($viewCollection->has(ProductAdmin::ADD_TABS_VIEW . '.attributes'));
    }

    public function testConfigureViewsWithActivityInsightsView(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)->willReturn(true);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)->willReturn(false);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)->willReturn(false);
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(false);
        $this->securityChecker->hasPermission(ActivityAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW)->willReturn(true);

        $insightsViewName = ProductAdmin::EDIT_TABS_VIEW . '.insights';

        $viewCollection = new ViewCollection();
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder($insightsViewName, '/insights'),
        );

        $this->admin->configureViews($viewCollection);

        $this->assertTrue($viewCollection->has($insightsViewName . '.activity'));
    }
}
