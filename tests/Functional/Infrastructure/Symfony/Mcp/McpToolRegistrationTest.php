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

namespace Sulu\Product\Tests\Functional\Infrastructure\Symfony\Mcp;

use Mcp\Capability\RegistryInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Application\Content\ContentTypeExtensionRegistry;
use Sulu\Mcp\Infrastructure\Mcp\FilteredRegistry;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

/**
 * Boots the product bundle together with the MCP bundle. Only a booted kernel shows the
 * resolved #[RequiresPermission] requirements, that the write tools are missing from the
 * registry while product_write is off, and that the content type extension is tagged.
 */
class McpToolRegistrationTest extends SuluTestCase
{
    private const WRITE_TOOLS = [
        'sulu_product_create',
        'sulu_product_update',
        'sulu_product_variant_create',
        'sulu_product_variant_update',
    ];

    private const READ_TOOLS = [
        'sulu_product_list',
        'sulu_product_get',
        'sulu_product_variant_list',
        'sulu_product_family_list',
        'sulu_attribute_list',
        'sulu_attribute_value_list',
        'sulu_product_get_products',
        'sulu_product_search_products_by_attributes',
    ];

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testToolPermissions(): void
    {
        self::bootKernel(['environment' => 'test_mcp_write']);

        $products = ProductAdmin::SECURITY_CONTEXT;
        $expected = [
            'sulu_product_list' => [[$products, PermissionTypes::VIEW]],
            'sulu_product_get' => [[$products, PermissionTypes::VIEW]],
            'sulu_product_variant_list' => [[$products, PermissionTypes::VIEW]],
            'sulu_product_family_list' => [[ProductFamilyAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW]],
            'sulu_attribute_list' => [[AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW]],
            'sulu_attribute_value_list' => [[AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW]],
            'sulu_product_get_products' => [[$products, PermissionTypes::VIEW]],
            'sulu_product_search_products_by_attributes' => [[$products, PermissionTypes::VIEW]],
            'sulu_product_create' => [[$products, PermissionTypes::EDIT], [$products, PermissionTypes::ADD]],
            'sulu_product_update' => [[$products, PermissionTypes::EDIT]],
            'sulu_product_variant_create' => [[$products, PermissionTypes::EDIT], [$products, PermissionTypes::ADD]],
            'sulu_product_variant_update' => [[$products, PermissionTypes::EDIT]],
        ];

        $actual = [];
        foreach ($this->getToolPermissions() as $name => $tool) {
            if (!\in_array($name, [...self::READ_TOOLS, ...self::WRITE_TOOLS], true)) {
                continue;
            }

            $actual[$name] = \array_map(
                static fn (array $requirement): array => [$requirement['context'], $requirement['permission']],
                $tool['requirements'],
            );
        }

        $this->assertEquals($expected, $actual);
    }

    public function testContentTypeExtensionRegistryResolvesProducts(): void
    {
        self::bootKernel(['environment' => 'test_mcp_write']);

        /** @var ContentTypeExtensionRegistry $registry */
        $registry = self::getContainer()->get(ContentTypeExtensionRegistry::class);

        $this->assertTrue($registry->has('products'));
        $this->assertSame('products', $registry->get('products')->getResourceKey());
    }

    public function testWriteToolsAreRegisteredWhenProductWriteIsEnabled(): void
    {
        self::bootKernel(['environment' => 'test_mcp_write']);

        $registered = $this->getRegisteredToolNames();

        foreach ([...self::READ_TOOLS, ...self::WRITE_TOOLS] as $name) {
            $this->assertContains($name, $registered);
        }
        $this->assertArrayHasKey('sulu_product_update', $this->getToolPermissions());
    }

    public function testWriteToolsAreAbsentWhenProductWriteIsDisabled(): void
    {
        self::bootKernel(['environment' => 'test_mcp_readonly']);

        $registered = $this->getRegisteredToolNames();
        $permissions = $this->getToolPermissions();

        foreach (self::READ_TOOLS as $name) {
            $this->assertContains($name, $registered);
        }
        foreach (self::WRITE_TOOLS as $name) {
            $this->assertNotContains($name, $registered);
            $this->assertArrayNotHasKey($name, $permissions);
        }
    }

    /**
     * @return array<string, array{requirements: list<array{context: string, permission: string}>}>
     */
    private function getToolPermissions(): array
    {
        /** @var array<string, array{requirements: list<array{context: string, permission: string}>}> $permissions */
        $permissions = self::getContainer()->getParameter('sulu_mcp.tool_permissions');

        return $permissions;
    }

    /**
     * @return list<int|string>
     */
    private function getRegisteredToolNames(): array
    {
        $container = self::getContainer();
        $container->get('mcp.server.sulu');

        // The undecorated registry: FilteredRegistry hides every tool the tokenless test request may not see.
        /** @var RegistryInterface $registry */
        $registry = $container->get(FilteredRegistry::class . '.inner');

        return \array_keys($registry->getTools()->references);
    }
}
