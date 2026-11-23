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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\RequestWorkflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\RequestWorkflow\PreValidator\RequestWorkflowPreValidatorInterface;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflow;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowResolverInterface;
use Sulu\Content\Application\RequestWorkflow\Validator\RequestWorkflowValidatorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\RequestWorkflow\VariantRequestWorkflowResolver;

#[CoversClass(VariantRequestWorkflowResolver::class)]
class VariantRequestWorkflowResolverTest extends TestCase
{
    public function testAVariantKeepsItsWorkflowWithoutTheRulesItCannotMeet(): void
    {
        $validators = ['unpublished_references' => ['validator' => $this->createMock(RequestWorkflowValidatorInterface::class), 'config' => [], 'required' => true]];
        $custom = ['pre_validator' => $this->createMock(RequestWorkflowPreValidatorInterface::class), 'config' => []];
        $workflow = new RequestWorkflow('default', $validators, 2, ['products'], [
            'seo_required' => ['pre_validator' => $this->createMock(RequestWorkflowPreValidatorInterface::class), 'config' => ['fields' => ['title']]],
            'excerpt_required' => ['pre_validator' => $this->createMock(RequestWorkflowPreValidatorInterface::class), 'config' => ['fields' => ['title']]],
            'publish_required' => ['pre_validator' => $this->createMock(RequestWorkflowPreValidatorInterface::class), 'config' => []],
            'custom_rule' => $custom,
        ]);
        $variantContent = new ProductDimensionContent((new Product('variant-uuid'))->setType(ProductInterface::TYPE_VARIANT)->setParent(new Product('parent-uuid')));

        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveForContent')->with($variantContent)->willReturn($workflow);

        $resolved = (new VariantRequestWorkflowResolver($inner))->resolveForContent($variantContent);

        $this->assertInstanceOf(RequestWorkflow::class, $resolved);
        $this->assertSame(['custom_rule' => $custom], $resolved->preValidators);
        $this->assertSame('default', $resolved->name);
        $this->assertSame($validators, $resolved->validators);
        $this->assertSame(2, $resolved->getRequiredUserApprovals());
        $this->assertSame(['products'], $resolved->resources);
    }

    public function testAVariantWithoutAWorkflowStaysWithout(): void
    {
        $variantContent = new ProductDimensionContent((new Product('variant-uuid'))->setType(ProductInterface::TYPE_VARIANT)->setParent(new Product('parent-uuid')));

        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->method('resolveForContent')->willReturn(null);

        $this->assertNull((new VariantRequestWorkflowResolver($inner))->resolveForContent($variantContent));
    }

    public function testAProductKeepsItsWorkflow(): void
    {
        $workflow = new RequestWorkflow('default', [], 1);
        $content = new ProductDimensionContent(new Product('product-uuid'));

        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveForContent')->with($content)->willReturn($workflow);

        $this->assertSame($workflow, (new VariantRequestWorkflowResolver($inner))->resolveForContent($content));
    }

    public function testOtherContentKeepsItsWorkflow(): void
    {
        $workflow = new RequestWorkflow('default', [], 1);
        $content = $this->createMock(DimensionContentInterface::class);

        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveForContent')->with($content)->willReturn($workflow);

        $this->assertSame($workflow, (new VariantRequestWorkflowResolver($inner))->resolveForContent($content));
    }

    public function testTheTemplateKeysComeFromTheInnerResolver(): void
    {
        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveTemplateKeysWithWorkflow')->with('products', 'product')->willReturn(['product']);

        $this->assertSame(['product'], (new VariantRequestWorkflowResolver($inner))->resolveTemplateKeysWithWorkflow('products', 'product'));
    }
}
