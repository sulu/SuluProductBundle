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
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

class ProductFamilyListTool
{
    private const ALLOWED_SORT_FIELDS = ['name', 'created', 'changed'];
    private const ALLOWED_SORT_ORDERS = ['asc', 'desc'];

    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_product_family_list',
        title: 'List Product Families',
        description: 'List product families. A family decides which attributes a product can carry, and its UUID is the mandatory "productFamily" argument of sulu_product_create. Each family lists its attributes with three flags that determine where a value belongs: "required" means a value must be supplied, and "variantSpecific" means the attribute is a variant axis — required variant-specific attributes are set on the variant (sulu_product_variant_create), all other required attributes on the product itself. Use "attributeId" as the key in any "attributes" map.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function listProductFamilies(
        string $locale,
        int $page = 1,
        int $limit = 20,
        #[Schema(description: 'Field to sort by. Defaults to "name".', enum: ['name', 'created', 'changed'])]
        string $sortBy = 'name',
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
            /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
            $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(ProductFamilyInterface::RESOURCE_KEY);

            /** @var DoctrineListBuilder $listBuilder */
            $listBuilder = $this->listBuilderFactory->create(ProductFamilyInterface::class);
            $listBuilder->setIdField($fieldDescriptors['id']);
            $listBuilder->setParameter('locale', $locale);
            // Not RestHelperInterface: it reads paging off the MCP transport request.
            $listBuilder->limit($limit);
            $listBuilder->setCurrentPage($page);

            if (isset($fieldDescriptors[$sortBy])) {
                $listBuilder->sort($fieldDescriptors[$sortBy], $sortOrder);
            }

            foreach (['id', 'name'] as $field) {
                if (isset($fieldDescriptors[$field])) {
                    $listBuilder->addSelectField($fieldDescriptors[$field]);
                }
            }

            $rows = $listBuilder->execute();
            $total = $listBuilder->count();

            $families = [];
            foreach ($rows as $row) {
                if (!\is_array($row)) {
                    continue;
                }

                // product_families.xml maps the uuid column to the property name "id".
                $uuid = \is_string($row['id'] ?? null) ? $row['id'] : null;
                if (null === $uuid) {
                    continue;
                }

                $families[] = [
                    'uuid' => $uuid,
                    'name' => $row['name'] ?? null,
                    'attributes' => $this->describeAttributes($uuid, $locale),
                ];
            }

            return [
                'families' => $families,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to list product families: %s', $e->getMessage()),
                'hint' => 'Verify SuluProductBundle is installed and its schema is up to date.',
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function describeAttributes(string $uuid, string $locale): array
    {
        $family = $this->productFamilyRepository->findOneBy(['uuid' => $uuid]);
        if (null === $family) {
            return [];
        }

        $attributes = [];
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();

            $attributes[] = [
                'attributeId' => $attribute->getId(),
                'key' => $attribute->getKey(),
                'type' => $attribute->getType(),
                'name' => $attribute->getTranslation($locale)?->getName() ?? $attribute->getKey(),
                'required' => $familyAttribute->isRequired(),
                'variantSpecific' => $familyAttribute->isVariantSpecific(),
            ];
        }

        return $attributes;
    }
}
