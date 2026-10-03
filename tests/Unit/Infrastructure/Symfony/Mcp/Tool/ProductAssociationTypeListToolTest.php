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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductAssociationTypeListTool;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductAssociationTypeListTool::class)]
final class ProductAssociationTypeListToolTest extends TestCase
{
    use ProphecyTrait;

    public function testItListsTheConfiguredTypesWithTranslatedLabels(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('sulu_product.association_type_accessory', [], 'admin', 'de')->willReturn('Zubehör');
        $translator->trans('Alternative', [], 'admin', 'de')->willReturn('Alternative');

        $tool = new ProductAssociationTypeListTool(
            new ProductAssociationTypeRegistry([
                'accessory' => ['label' => 'sulu_product.association_type_accessory'],
                'alternative' => ['label' => 'Alternative'],
            ]),
            $translator->reveal(),
        );

        $this->assertSame([
            'associationTypes' => [
                ['key' => 'accessory', 'label' => 'Zubehör'],
                ['key' => 'alternative', 'label' => 'Alternative'],
            ],
        ], $tool->listAssociationTypes('de'));
    }

    public function testItExplainsAnEmptyList(): void
    {
        $tool = new ProductAssociationTypeListTool(new ProductAssociationTypeRegistry([]), $this->prophesize(TranslatorInterface::class)->reveal());

        $result = $tool->listAssociationTypes('en');

        $this->assertSame([], $result['associationTypes']);
        $this->assertArrayHasKey('hint', $result);
    }

    public function testMethodHasMcpToolAttribute(): void
    {
        $attributes = (new \ReflectionMethod(ProductAssociationTypeListTool::class, 'listAssociationTypes'))->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('sulu_product_association_type_list', $attributes[0]->newInstance()->name);
    }
}
