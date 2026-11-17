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
use Sulu\Content\Application\RequestWorkflow\RequestWorkflow;
use Sulu\Content\Application\RequestWorkflow\RequestWorkflowResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\RequestWorkflow\VariantExcludedRequestWorkflowResolver;

#[CoversClass(VariantExcludedRequestWorkflowResolver::class)]
class VariantExcludedRequestWorkflowResolverTest extends TestCase
{
    public function testAVariantHasNoWorkflow(): void
    {
        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->never())->method('resolveForContent');

        $variant = (new Product('variant-uuid'))->setParent(new Product('parent-uuid'));

        $this->assertNull((new VariantExcludedRequestWorkflowResolver($inner))->resolveForContent(new ProductDimensionContent($variant)));
    }

    public function testAProductKeepsItsWorkflow(): void
    {
        $workflow = new RequestWorkflow('default', [], 1);
        $content = new ProductDimensionContent(new Product('product-uuid'));

        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveForContent')->with($content)->willReturn($workflow);

        $this->assertSame($workflow, (new VariantExcludedRequestWorkflowResolver($inner))->resolveForContent($content));
    }

    public function testOtherContentKeepsItsWorkflow(): void
    {
        $workflow = new RequestWorkflow('default', [], 1);
        $content = $this->createMock(DimensionContentInterface::class);

        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveForContent')->with($content)->willReturn($workflow);

        $this->assertSame($workflow, (new VariantExcludedRequestWorkflowResolver($inner))->resolveForContent($content));
    }

    public function testTheTemplateKeysComeFromTheInnerResolver(): void
    {
        $inner = $this->createMock(RequestWorkflowResolverInterface::class);
        $inner->expects($this->once())->method('resolveTemplateKeysWithWorkflow')->with('products', 'product')->willReturn(['product']);

        $this->assertSame(['product'], (new VariantExcludedRequestWorkflowResolver($inner))->resolveTemplateKeysWithWorkflow('products', 'product'));
    }
}
