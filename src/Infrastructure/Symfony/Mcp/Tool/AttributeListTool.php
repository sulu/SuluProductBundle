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
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

/**
 * @internal
 */
final class AttributeListTool
{
    private const ALLOWED_SORT_FIELDS = ['key', 'id'];
    private const ALLOWED_SORT_ORDERS = ['asc', 'desc'];

    public function __construct(
        private readonly AttributeGroupRepositoryInterface $attributeGroupRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_attribute_list',
        title: 'List Product Attributes',
        description: 'List product attributes, paginated. Each entry names its attribute group. The "id" of each attribute is the key to use in the "attributes" map of sulu_product_create, sulu_product_update and the variant tools, e.g. an attribute with id 12 is written as {"12": "red"}. "type" tells you what a value looks like: "text" a string, "number" a number, "date" an ISO-8601 date, "options" one of the listed option keys. Which attributes actually apply to a given product, and which are required or variant axes, depends on its family. See sulu_product_family_list.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function listAttributes(
        string $locale,
        int $page = 1,
        int $limit = 50,
        #[Schema(description: 'Field to sort by. Defaults to "key".', enum: ['key', 'id'])]
        string $sortBy = 'key',
        #[Schema(description: 'Sort direction, "asc" or "desc". Defaults to "asc".', enum: ['asc', 'desc'])]
        string $sortOrder = 'asc',
    ): array {
        if (!\in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sortBy "%s". Supported: %s.', $sortBy, \implode(', ', self::ALLOWED_SORT_FIELDS)));
        }

        if (!\in_array($sortOrder, self::ALLOWED_SORT_ORDERS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sortOrder "%s". Supported: %s.', $sortOrder, \implode(', ', self::ALLOWED_SORT_ORDERS)));
        }

        try {
            $attributes = [];

            $groups = $this->attributeGroupRepository->findBy(selects: [
                AttributeGroupRepositoryInterface::SELECT_GROUP_TRANSLATIONS => true,
                AttributeGroupRepositoryInterface::SELECT_GROUP_ATTRIBUTES => true,
                AttributeGroupRepositoryInterface::SELECT_GROUP_ATTRIBUTE_TRANSLATIONS => true,
            ]);

            foreach ($groups as $group) {
                $groupName = $group->getTranslation($locale)?->getName();

                foreach ($group->getGroupAttributes() as $groupAttribute) {
                    $attributes[] = $this->describeAttribute($groupAttribute->getAttribute(), $locale) + [
                        'group' => $groupName,
                        'groupUuid' => $group->getUuid(),
                    ];
                }
            }

            \usort($attributes, static function(array $a, array $b) use ($sortBy, $sortOrder): int {
                $comparison = $a[$sortBy] <=> $b[$sortBy];

                return 'desc' === $sortOrder ? -$comparison : $comparison;
            });

            $total = \count($attributes);

            return [
                // The repository has no paginated query: this bounds the response, not the rows read.
                'attributes' => \array_slice($attributes, ($page - 1) * $limit, $limit),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to list attributes: %s', $e->getMessage()),
                'hint' => 'Verify SuluProductBundle is installed and its schema is up to date.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describeAttribute(AttributeInterface $attribute, string $locale): array
    {
        $described = [
            'id' => $attribute->getId(),
            'key' => $attribute->getKey(),
            'type' => $attribute->getType(),
            'name' => $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey(),
            'localized' => $attribute->isLocalized(),
            'position' => $attribute->getPosition(),
        ];

        if (AttributeInterface::TYPE_OPTIONS === $attribute->getType()) {
            $options = [];
            foreach ($attribute->getOptions() as $option) {
                $options[] = [
                    'key' => $option->getKey(),
                    'name' => $option->getTranslation($locale)?->getName() ?? $option->getKey(),
                ];
            }

            $described['options'] = $options;
        }

        return $described;
    }
}
