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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductCodeFormMetadataVisitor;

#[CoversClass(ProductCodeFormMetadataVisitor::class)]
class ProductDetailsFormSchemaTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testCodeRequirementIsBranchedOnProductType(): void
    {
        self::bootKernel();

        /** @var FormMetadataProvider $provider */
        $provider = self::getContainer()->get('sulu_admin.form_metadata_provider');

        $metadata = $provider->getMetadata('product_details', 'en');
        $this->assertInstanceOf(FormMetadata::class, $metadata);

        $schema = \json_encode($metadata->getSchema()->toJsonSchema());
        $this->assertIsString($schema);

        $this->assertStringContainsString(
            '{"type":"object","properties":{"type":{"const":"product"},"code":{"type":"string","minLength":1}},"required":["code"]}',
            $schema,
        );
        $this->assertStringContainsString(
            '{"type":"object","properties":{"type":{"const":"product_with_variants"}}}',
            $schema,
        );
    }

    public function testCodeIsVisibleForEveryProductType(): void
    {
        self::bootKernel();

        /** @var FormMetadataProvider $provider */
        $provider = self::getContainer()->get('sulu_admin.form_metadata_provider');

        $metadata = $provider->getMetadata('product_details', 'en');
        $this->assertInstanceOf(FormMetadata::class, $metadata);

        $code = $metadata->getItems()['code'] ?? null;
        $this->assertInstanceOf(FieldMetadata::class, $code);
        $this->assertNull($code->getVisibleCondition());
    }
}
