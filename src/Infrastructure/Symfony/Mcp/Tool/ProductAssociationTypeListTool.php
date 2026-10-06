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

namespace Sulu\Product\Infrastructure\Symfony\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class ProductAssociationTypeListTool
{
    public function __construct(
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{associationTypes: list<array{key: string, label: string}>, hint?: string}
     */
    #[McpTool(
        name: 'sulu_product_association_type_list',
        title: 'List Product Association Types',
        description: 'List the association types this installation defines, e.g. links from a product to its accessories or alternatives. Use a "key" as the key of the "associations" argument of sulu_product_create and sulu_product_update. The same keys come back in "associations" of sulu_product_get.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function listAssociationTypes(string $locale): array
    {
        $types = [];
        foreach ($this->associationTypeRegistry->getTypes() as $type) {
            $types[] = [
                'key' => $type->getKey(),
                'label' => $this->translator->trans($type->getLabel(), [], 'admin', $locale),
            ];
        }

        if ([] === $types) {
            return [
                'associationTypes' => [],
                'hint' => 'This installation defines no association types, so products cannot be linked to each other.',
            ];
        }

        return ['associationTypes' => $types];
    }
}
