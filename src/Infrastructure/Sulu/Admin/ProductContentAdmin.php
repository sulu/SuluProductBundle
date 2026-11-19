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
use Sulu\Bundle\AdminBundle\Admin\View\FormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\PreviewFormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * @final
 *
 * @internal
 */
class ProductContentAdmin extends Admin
{
    private const TAB_ORDERS = [
        ProductAdmin::EDIT_TABS_VIEW . '.content' => 40,
        ProductAdmin::EDIT_TABS_VIEW . '.seo' => 50,
        ProductAdmin::EDIT_TABS_VIEW . '.excerpt' => 60,
        ProductAdmin::EDIT_TABS_VIEW . '.settings' => 70,
    ];

    public function __construct(
        private ContentViewBuilderFactoryInterface $contentViewBuilderFactory,
        private SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $toolbarActions = $this->contentViewBuilderFactory->getDefaultToolbarActions(ProductInterface::class);

        $viewBuilders = $this->contentViewBuilderFactory->createViews(
            ProductInterface::class,
            ProductAdmin::EDIT_TABS_VIEW,
            null,
            ProductAdmin::SECURITY_CONTEXT,
            $toolbarActions,
        );

        foreach ($viewBuilders as $viewBuilder) {
            if ($viewBuilder instanceof PreviewFormViewBuilderInterface) {
                $viewBuilder->setPreviewCondition('workflowPlace != null');
            }

            $this->applyTabOrder($viewBuilder);
            $this->addIdToExcerptMetadata($viewBuilder);

            $viewCollection->add($viewBuilder);
        }
    }

    /**
     * The content views default to orders that collide with details (10), variants (20) and
     * associations (30).
     */
    private function applyTabOrder(ViewBuilderInterface $viewBuilder): void
    {
        $tabOrder = self::TAB_ORDERS[$viewBuilder->getName()] ?? null;

        if (null === $tabOrder) {
            return;
        }

        if ($viewBuilder instanceof PreviewFormViewBuilderInterface || $viewBuilder instanceof FormViewBuilderInterface) {
            $viewBuilder->setTabOrder($tabOrder);
        }
    }

    /**
     * The excerpt metadata is built per request, so with the id and the content locale the main category
     * can be locked in the excerpt categories (see ProductExcerptFormMetadataVisitor). The locale the
     * metadata gets itself is the one of the user interface.
     */
    private function addIdToExcerptMetadata(ViewBuilderInterface $viewBuilder): void
    {
        if (ProductAdmin::EDIT_TABS_VIEW . '.excerpt' !== $viewBuilder->getName()) {
            return;
        }

        if ($viewBuilder instanceof PreviewFormViewBuilderInterface || $viewBuilder instanceof FormViewBuilderInterface) {
            $viewBuilder->addRouterAttributesToFormMetadata(['id' => 'id', 'locale' => 'locale']);
        }
    }

    public static function getPriority(): int
    {
        return 20;
    }
}
