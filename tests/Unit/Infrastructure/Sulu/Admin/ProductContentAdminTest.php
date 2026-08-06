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
use Sulu\Bundle\AdminBundle\Admin\View\FormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\PreviewFormViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactoryInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductContentAdmin;

#[CoversClass(ProductContentAdmin::class)]
class ProductContentAdminTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ContentViewBuilderFactoryInterface> */
    private ObjectProphecy $contentViewBuilderFactory;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    private ProductContentAdmin $admin;

    protected function setUp(): void
    {
        $this->contentViewBuilderFactory = $this->prophesize(ContentViewBuilderFactoryInterface::class);
        $this->securityChecker = $this->prophesize(SecurityCheckerInterface::class);

        $this->admin = new ProductContentAdmin(
            $this->contentViewBuilderFactory->reveal(),
            $this->securityChecker->reveal(),
        );
    }

    public function testGetPriority(): void
    {
        $this->assertSame(20, ProductContentAdmin::getPriority());
    }

    public function testConfigureViewsSkippedWithoutPermission(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(false);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(0, $viewCollection->all());
    }

    public function testConfigureViewsAddsViewsWithPermission(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);

        $this->contentViewBuilderFactory->getDefaultToolbarActions(ProductInterface::class)
            ->willReturn([]);

        $regularView = $this->prophesize(ViewBuilderInterface::class);
        $regularView->getName()->willReturn('content.view');

        $previewView = $this->prophesize(PreviewFormViewBuilderInterface::class);
        $previewView->getName()->willReturn('content.preview');
        $previewView->setPreviewCondition('workflowPlace != null')->shouldBeCalled();

        $this->contentViewBuilderFactory->createViews(
            ProductInterface::class,
            ProductAdmin::EDIT_TABS_VIEW,
            null,
            ProductAdmin::SECURITY_CONTEXT,
            Argument::any(),
        )->willReturn([$regularView->reveal(), $previewView->reveal()]);

        $viewCollection = new ViewCollection();
        $this->admin->configureViews($viewCollection);

        $this->assertCount(2, $viewCollection->all());
    }

    /**
     * The content views ship with tab orders that collide with the product's own tabs
     * (content defaults to 20, the same as variants; seo to 30, the same as associations).
     */
    public function testContentViewsAreOrderedBehindTheProductsOwnTabs(): void
    {
        $this->securityChecker->hasPermission(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT)
            ->willReturn(true);

        $this->contentViewBuilderFactory->getDefaultToolbarActions(ProductInterface::class)
            ->willReturn([]);

        $expectedOrders = [
            ProductAdmin::EDIT_TABS_VIEW . '.content' => 40,
            ProductAdmin::EDIT_TABS_VIEW . '.seo' => 50,
            ProductAdmin::EDIT_TABS_VIEW . '.excerpt' => 60,
            ProductAdmin::EDIT_TABS_VIEW . '.settings' => 70,
        ];

        $viewBuilders = [];
        foreach ($expectedOrders as $name => $tabOrder) {
            $viewBuilder = $this->prophesize(FormViewBuilderInterface::class);
            $viewBuilder->getName()->willReturn($name);
            $viewBuilder->setTabOrder($tabOrder)->shouldBeCalled();
            $viewBuilders[] = $viewBuilder->reveal();
        }

        // the insights views carry no tab order of ours and must be left alone
        $insights = $this->prophesize(FormViewBuilderInterface::class);
        $insights->getName()->willReturn(ProductAdmin::EDIT_TABS_VIEW . '.insights');
        $insights->setTabOrder(Argument::any())->shouldNotBeCalled();
        $viewBuilders[] = $insights->reveal();

        $this->contentViewBuilderFactory->createViews(
            ProductInterface::class,
            ProductAdmin::EDIT_TABS_VIEW,
            null,
            ProductAdmin::SECURITY_CONTEXT,
            Argument::any(),
        )->willReturn($viewBuilders);

        $this->admin->configureViews(new ViewCollection());
    }
}
