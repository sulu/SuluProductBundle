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
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\FormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ListViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ResourceTabViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

#[CoversClass(AttributeAdmin::class)]
class AttributeAdminTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ViewBuilderFactoryInterface> */
    private ObjectProphecy $viewBuilderFactory;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<LocalizationManagerInterface> */
    private ObjectProphecy $localizationManager;

    private AttributeAdmin $admin;

    protected function setUp(): void
    {
        $this->viewBuilderFactory = $this->prophesize(ViewBuilderFactoryInterface::class);
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);
        $this->localizationManager = $this->prophesize(LocalizationManagerInterface::class);
        $this->localizationManager->getLocales()->willReturn(['en', 'de']);

        $this->admin = new AttributeAdmin(
            $this->viewBuilderFactory->reveal(),
            $this->securityChecker->reveal(),
            $this->localizationManager->reveal(),
        );
    }

    public function testConfigureNavigationItemsIsNoOp(): void
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
        $this->assertArrayHasKey(AttributeAdmin::SECURITY_CONTEXT, $contexts['Sulu']['Product']);
        $this->assertSame([
            PermissionTypes::VIEW,
            PermissionTypes::ADD,
            PermissionTypes::EDIT,
            PermissionTypes::DELETE,
        ], $contexts['Sulu']['Product'][AttributeAdmin::SECURITY_CONTEXT]);
    }

    public function testConfigureViewsSkippedWithoutEditPermission(): void
    {
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(0, $viewCollection->all());
    }

    public function testConfigureViewsWithFullPermissions(): void
    {
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ->willReturn(true);

        $this->prepareFluentBuilders();

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertGreaterThan(0, \count($viewCollection->all()));
    }

    public function testConfigureViewsWithEditOnly(): void
    {
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::ADD)
            ->willReturn(false);
        $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::DELETE)
            ->willReturn(false);

        $this->prepareFluentBuilders();

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertGreaterThan(0, \count($viewCollection->all()));
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
        $listBuilder->getName()->willReturn(AttributeAdmin::LIST_VIEW);

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
