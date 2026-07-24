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

use Sulu\Bundle\ActivityBundle\Infrastructure\Sulu\Admin\View\ActivityViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\DropdownToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * @final
 *
 * @internal
 */
class ProductAdmin extends Admin
{
    public const SECURITY_CONTEXT = 'sulu.product.products';
    public const LIST_VIEW = 'sulu_product.product.list';
    public const ADD_TABS_VIEW = 'sulu_product.product.add_tabs';
    public const EDIT_TABS_VIEW = 'sulu_product.product.edit_tabs';

    public function __construct(
        private ViewBuilderFactoryInterface $viewBuilderFactory,
        private SecurityCheckerInterface $securityChecker,
        private LocalizationManagerInterface $localizationManager,
        private ActivityViewBuilderFactoryInterface $activityViewBuilderFactory,
        private ProductAssociationTypeRegistry $associationTypeRegistry,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        $hasProducts = $this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT);
        $hasAttributes = $this->securityChecker->hasPermission(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT);
        $hasAttributeGroups = $this->securityChecker->hasPermission(AttributeGroupAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT);
        $hasProductFamilies = $this->securityChecker->hasPermission(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT);

        if (!$hasProducts && !$hasAttributes && !$hasAttributeGroups && !$hasProductFamilies) {
            return;
        }

        $parent = new NavigationItem('sulu_product.products');
        $parent->setPosition(20);
        $parent->setIcon('su-newspaper');

        if ($hasProducts) {
            $productsChild = new NavigationItem('sulu_product.products');
            $productsChild->setView(static::LIST_VIEW);
            $parent->addChild($productsChild);
        }

        if ($hasAttributes) {
            $attributesChild = new NavigationItem('sulu_product.attributes');
            $attributesChild->setView(AttributeAdmin::LIST_VIEW);
            $parent->addChild($attributesChild);
        }

        if ($hasAttributeGroups) {
            $attributeGroupsItem = new NavigationItem('sulu_product.attribute_groups');
            $attributeGroupsItem->setView(AttributeGroupAdmin::LIST_VIEW);
            $parent->addChild($attributeGroupsItem);
        }

        if ($hasProductFamilies) {
            $productFamiliesItem = new NavigationItem('sulu_product.product_families');
            $productFamiliesItem->setView(ProductFamilyAdmin::LIST_VIEW);
            $parent->addChild($productFamiliesItem);
        }

        $navigationItemCollection->add($parent);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $locales = $this->localizationManager->getLocales();

        // List view
        $listToolbarActions = [];
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.add');
        }
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            $listToolbarActions[] = new ToolbarAction('sulu_admin.export');
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(static::LIST_VIEW, '/:locale/products')
                ->setResourceKey(ProductInterface::RESOURCE_KEY)
                ->setListKey(ProductInterface::LIST_KEY)
                ->addListAdapters(['table'])
                ->addLocales($locales)
                ->setDefaultLocale($locales[0] ?? '')
                ->setAddView(static::ADD_TABS_VIEW)
                ->setEditView(static::EDIT_TABS_VIEW)
                ->addToolbarActions($listToolbarActions),
        );

        // Add tab container
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::ADD_TABS_VIEW, '/:locale/products/add')
                ->setResourceKey(ProductInterface::RESOURCE_KEY)
                ->addLocales($locales)
                ->setBackView(static::LIST_VIEW),
        );

        // Edit tab container
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(static::EDIT_TABS_VIEW, '/:locale/products/:id')
                ->setResourceKey(ProductInterface::RESOURCE_KEY)
                ->addLocales($locales)
                ->setBackView(static::LIST_VIEW)
                ->setTitleProperty('name'),
        );

        // Details form — add mode
        $addToolbarActions = [new ToolbarAction('sulu_admin.save')];
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder(static::ADD_TABS_VIEW . '.details', '/details')
                ->setResourceKey(ProductInterface::RESOURCE_KEY)
                ->setFormKey(ProductInterface::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->setTabOrder(10)
                ->addToolbarActions($addToolbarActions)
                ->setEditView(static::EDIT_TABS_VIEW)
                ->setParent(static::ADD_TABS_VIEW),
        );

        // Details form — edit mode
        $editToolbarActions = [
            new ToolbarAction(
                'sulu_admin.save_with_publishing',
                [
                    'publish_visible_condition' => '(!_permissions || _permissions.live)',
                    'save_visible_condition' => '(!_permissions || _permissions.edit)',
                ]
            ),
        ];
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $editToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }
        if ($this->securityChecker->hasPermission(static::SECURITY_CONTEXT, PermissionTypes::LIVE)) {
            $editToolbarActions[] = new DropdownToolbarAction(
                'sulu_admin.edit',
                'su-pen',
                [
                    new ToolbarAction(
                        'sulu_admin.delete_draft',
                        ['visible_condition' => '(!_permissions || _permissions.live)']
                    ),
                    new ToolbarAction(
                        'sulu_admin.set_unpublished',
                        ['visible_condition' => '(!_permissions || _permissions.live)']
                    ),
                ]
            );
        }
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder(static::EDIT_TABS_VIEW . '.details', '/details')
                ->setResourceKey(ProductInterface::RESOURCE_KEY)
                ->setFormKey(ProductInterface::FORM_KEY)
                ->setTabTitle('sulu_admin.details')
                ->setTabOrder(10)
                ->addRouterAttributesToFormMetadata(['id'])
                ->addToolbarActions($editToolbarActions)
                ->setParent(static::EDIT_TABS_VIEW),
        );

        // Associations form — edit mode only, when at least one association type is configured
        if ([] !== $this->associationTypeRegistry->getTypes()) {
            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(static::EDIT_TABS_VIEW . '.associations', '/associations')
                    ->setResourceKey(ProductInterface::RESOURCE_KEY)
                    ->setFormKey('product_associations')
                    ->setTabTitle('sulu_product.associations')
                    ->setTabOrder(20)
                    ->addRouterAttributesToFormMetadata(['id'])
                    ->addToolbarActions($editToolbarActions)
                    ->setParent(static::EDIT_TABS_VIEW),
            );
        }

        // Activity tab (added by activityViewBuilderFactory if it has permission)
        $insightsViewName = static::EDIT_TABS_VIEW . '.insights';
        if ($viewCollection->has($insightsViewName) && $this->activityViewBuilderFactory->hasActivityListPermission()) {
            $viewCollection->add(
                $this->activityViewBuilderFactory
                    ->createActivityListViewBuilder(
                        $insightsViewName . '.activity',
                        '/activities',
                        ProductInterface::RESOURCE_KEY,
                    )
                    ->setParent($insightsViewName),
            );
        }
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
                        PermissionTypes::LIVE,
                    ],
                ],
            ],
        ];
    }
}
