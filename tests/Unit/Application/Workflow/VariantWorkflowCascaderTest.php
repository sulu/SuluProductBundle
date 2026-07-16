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
use Sulu\Product\Application\Workflow\VariantWorkflowCascader;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class VariantWorkflowCascaderTest extends TestCase
{
    use ProphecyTrait;

    private const EAGER = [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
        'selects' => [],
        'dimensionAttributes' => [
            'locale' => 'en',
            'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
        ],
    ]];

    public function testDoesNotCascadeNonPublishTransition(): void
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findBy(Argument::cetera())->shouldNotBeCalled();
        $workflow = $this->prophesize(ContentWorkflowInterface::class);

        $cascader = new VariantWorkflowCascader($repository->reveal(), $workflow->reveal());
        $cascader->cascade(new Product('p'), WorkflowInterface::WORKFLOW_TRANSITION_EDIT, 'en');
    }

    public function testCascadesPublishToEachVariant(): void
    {
        $parent = new Product('p');
        $variantA = new Product('a');
        $variantB = new Product('b');

        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findBy(['parent' => 'p'], [], self::EAGER)->willReturn([$variantA, $variantB]);

        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply($variantA, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)->shouldBeCalledOnce();
        $workflow->apply($variantB, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)->shouldBeCalledOnce();

        $cascader = new VariantWorkflowCascader($repository->reveal(), $workflow->reveal());
        $cascader->cascade($parent, WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH, 'en');
    }

    public function testSkipsVariantWhoseTransitionIsNotEnabled(): void
    {
        $parent = new Product('p');
        $alreadyPublished = new Product('a');
        $draft = new Product('b');

        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findBy(['parent' => 'p'], [], self::EAGER)->willReturn([$alreadyPublished, $draft]);

        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply($alreadyPublished, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)
            ->willThrow(new UnavailableContentTransitionException('already published'));
        $workflow->apply($draft, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)
            ->shouldBeCalledOnce();

        $cascader = new VariantWorkflowCascader($repository->reveal(), $workflow->reveal());
        $cascader->cascade($parent, WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH, 'en');
    }

    public function testSkipsVariantWithNoContentInTheTargetLocale(): void
    {
        $parent = new Product('p');
        $noContent = new Product('a');
        $withContent = new Product('b');

        $eagerDe = [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
            'selects' => [],
            'dimensionAttributes' => [
                'locale' => 'de',
                'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
            ],
        ]];

        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findBy(['parent' => 'p'], [], $eagerDe)->willReturn([$noContent, $withContent]);

        $workflow = $this->prophesize(ContentWorkflowInterface::class);
        $workflow->apply($noContent, ['locale' => 'de'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)
            ->willThrow(new ContentNotFoundException($noContent, ['locale' => 'de']));
        $workflow->apply($withContent, ['locale' => 'de'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)
            ->shouldBeCalledOnce();

        $cascader = new VariantWorkflowCascader($repository->reveal(), $workflow->reveal());
        $cascader->cascade($parent, WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH, 'de');
    }
}
