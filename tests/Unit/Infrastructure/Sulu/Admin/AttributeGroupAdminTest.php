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
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;

#[CoversClass(AttributeGroupAdmin::class)]
class AttributeGroupAdminTest extends TestCase
{
    use ProphecyTrait;

    private ViewBuilderFactory $viewBuilderFactory;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<LocalizationManagerInterface> */
    private ObjectProphecy $localizationManager;

    private AttributeGroupAdmin $admin;

    protected function setUp(): void
    {
        $this->viewBuilderFactory = new ViewBuilderFactory();
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->localizationManager = $this->prophesize(LocalizationManagerInterface::class);
        $this->localizationManager->getLocales()->willReturn(['en', 'de']);

        $this->admin = new AttributeGroupAdmin(
            $this->viewBuilderFactory,
            $this->securityChecker->reveal(),
            $this->localizationManager->reveal(),
        );
    }

    public function testConfigureNavigationItemsIsEmpty(): void
    {
        $collection = new NavigationItemCollection();

        $this->admin->configureNavigationItems($collection);

        $this->assertSame([], $collection->all());
    }

    public function testGetSecurityContexts(): void
    {
        $contexts = $this->admin->getSecurityContexts();

        $this->assertArrayHasKey('Sulu', $contexts);
        $this->assertArrayHasKey('Product', $contexts['Sulu']);
        $this->assertArrayHasKey(AttributeGroupAdmin::SECURITY_CONTEXT, $contexts['Sulu']['Product']);
        $this->assertSame([
            PermissionTypes::VIEW,
            PermissionTypes::ADD,
            PermissionTypes::EDIT,
            PermissionTypes::DELETE,
        ], $contexts['Sulu']['Product'][AttributeGroupAdmin::SECURITY_CONTEXT]);
    }

    public function testConfigureViewsSkippedWithoutEditPermission(): void
    {
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(0, $viewCollection->all());
    }

    public function testConfigureViewsAddsListView(): void
    {
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)
            ->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::LIST_VIEW));
        $this->assertCount(5, $viewCollection->all());
    }

    public function testConfigureViewsAddsAddTabsView(): void
    {
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)
            ->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::ADD_TABS_VIEW));
    }

    public function testConfigureViewsAddsEditTabsView(): void
    {
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)
            ->willReturn(false);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::EDIT_TABS_VIEW));
    }

    public function testConfigureViewsWithFullPermissions(): void
    {
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ->willReturn(true);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(5, $viewCollection->all());
        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::LIST_VIEW));
        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::ADD_TABS_VIEW));
        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::EDIT_TABS_VIEW));
        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::ADD_TABS_VIEW . '.details'));
        $this->assertTrue($viewCollection->has(AttributeGroupAdmin::EDIT_TABS_VIEW . '.details'));
    }
}
