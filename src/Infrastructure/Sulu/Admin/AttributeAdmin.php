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

namespace Sulu\Product\Infrastructure\Sulu\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Product\Domain\Model\AttributeInterface;

/**
 * @final
 *
 * @internal
 */
class AttributeAdmin extends Admin
{
    public const SECURITY_CONTEXT = 'sulu.product.attributes';
    public const LIST_VIEW = 'sulu_product.attribute.list';
    public const ADD_TABS_VIEW = 'sulu_product.attribute.add_tabs';
    public const EDIT_TABS_VIEW = 'sulu_product.attribute.edit_tabs';

    public function __construct(
        private ViewBuilderFactoryInterface $viewBuilderFactory,
        private SecurityCheckerInterface $securityChecker,
        private LocalizationManagerInterface $localizationManager,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        // Navigation is managed by ProductAdmin
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $locales = $this->localizationManager->getLocales();

        $listToolbarActions = [];
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(static::LIST_VIEW, '/:locale/attributes')
                ->setResourceKey(AttributeInterface::RESOURCE_KEY)
                ->setListKey(AttributeInterface::LIST_KEY)
                ->addListAdapters(['table'])
                ->addLocales($locales)
                ->setDefaultLocale($locales[0] ?? '')
                ->setAddView(static::ADD_TABS_VIEW)
                ->setEditView(static::EDIT_TABS_VIEW)
                ->addToolbarActions($listToolbarActions),
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::ADD_TABS_VIEW, '/:locale/attributes/add')
                ->setResourceKey(AttributeInterface::RESOURCE_KEY)
                ->addLocales($locales)
                ->setBackView(static::LIST_VIEW),
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::EDIT_TABS_VIEW, '/:locale/attributes/:id')
                ->setResourceKey(AttributeInterface::RESOURCE_KEY)
                ->addLocales($locales)
                ->setBackView(static::LIST_VIEW)
                ->setTitleProperty('name'),
        );

        $addToolbarActions = [new ToolbarAction('sulu_admin.save')];
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder(static::ADD_TABS_VIEW . '.details', '/details')
                ->setResourceKey(AttributeInterface::RESOURCE_KEY)
                ->setFormKey(AttributeInterface::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->setTabOrder(10)
                ->addToolbarActions($addToolbarActions)
                ->setEditView(static::EDIT_TABS_VIEW)
                ->setParent(static::ADD_TABS_VIEW),
        );

        $editToolbarActions = [new ToolbarAction('sulu_admin.save')];
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $editToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder(static::EDIT_TABS_VIEW . '.details', '/details')
                ->setResourceKey(AttributeInterface::RESOURCE_KEY)
                ->setFormKey(AttributeInterface::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->setTabOrder(10)
                ->addToolbarActions($editToolbarActions)
                ->setParent(static::EDIT_TABS_VIEW),
        );
    }

    public function getSecurityContexts(): array
    {
        return [
            'Sulu' => [
                'Product' => [
                    static::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
