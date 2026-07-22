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

namespace Sulu\Product\Tests\Unit\Application\Workflow;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Product\Application\Workflow\VariantParentPublishStateUpdater;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class VariantParentPublishStateUpdaterTest extends TestCase
{
    use ProphecyTrait;

    public function testNoopForTopLevelProduct(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply(Argument::cetera())->shouldNotBeCalled();

        $updater = new VariantParentPublishStateUpdater($repository->reveal(), $workflow->reveal());
        $updater->markParentAsChanged(new Product(), 'en');
    }

    public function testAppliesEditToParent(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $parentWithContent = new Product('11111111-1111-7111-8111-111111111111');

        $variant = new Product();
        $variant->setParent($parent);

        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->getOneBy(
            ['uuid' => $parent->getUuid()],
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [],
                'dimensionAttributes' => [
                    'locale' => 'en',
                    'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                ],
            ]],
        )->willReturn($parentWithContent);

        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply($parentWithContent, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_EDIT)
            ->shouldBeCalledOnce();

        $updater = new VariantParentPublishStateUpdater($repository->reveal(), $workflow->reveal());
        $updater->markParentAsChanged($variant, 'en');
    }

    public function testDoesNotPropagateWhenParentTransitionIsNotAvailable(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $parentWithContent = new Product('11111111-1111-7111-8111-111111111111');

        $variant = new Product();
        $variant->setParent($parent);

        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->getOneBy(Argument::cetera())->willReturn($parentWithContent);

        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply($parentWithContent, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_EDIT)
            ->willThrow(new UnavailableContentTransitionException('not enabled'))
            ->shouldBeCalledOnce();

        $updater = new VariantParentPublishStateUpdater($repository->reveal(), $workflow->reveal());
        $updater->markParentAsChanged($variant, 'en');
    }

    public function testDoesNotPropagateWhenParentHasNoContentInLocale(): void
    {
        $parent = new Product('11111111-1111-7111-8111-111111111111');
        $parentWithContent = new Product('11111111-1111-7111-8111-111111111111');

        $variant = new Product();
        $variant->setParent($parent);

        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->getOneBy(Argument::cetera())->willReturn($parentWithContent);

        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply($parentWithContent, ['locale' => 'de'], WorkflowInterface::WORKFLOW_TRANSITION_EDIT)
            ->willThrow(new ContentNotFoundException($parentWithContent, ['locale' => 'de']))
            ->shouldBeCalledOnce();

        $updater = new VariantParentPublishStateUpdater($repository->reveal(), $workflow->reveal());
        $updater->markParentAsChanged($variant, 'de');
    }
}
