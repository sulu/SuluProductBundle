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
use Sulu\Bundle\AdminBundle\Admin\View\PreviewFormViewBuilderInterface;
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
            $viewCollection->add($viewBuilder);
        }
    }

    public static function getPriority(): int
    {
        return 20;
    }
}
