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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductCodeFormMetadataVisitor;

#[CoversClass(ProductCodeFormMetadataVisitor::class)]
class ProductCodeFormMetadataVisitorTest extends TestCase
{
    public function testLeavesOtherFormsUntouched(): void
    {
        $formMetadata = new FormMetadata();
        $formMetadata->setKey('product_variant');
        $formMetadata->setSchema(new SchemaMetadata());

        (new ProductCodeFormMetadataVisitor())->visitFormMetadata($formMetadata, 'en');

        $this->assertArrayNotHasKey('allOf', $formMetadata->getSchema()->toJsonSchema());
    }

    public function testCodeIsRequiredForPlainProductsOnly(): void
    {
        $formMetadata = new FormMetadata();
        $formMetadata->setKey('product_details');
        $formMetadata->setSchema(new SchemaMetadata());

        (new ProductCodeFormMetadataVisitor())->visitFormMetadata($formMetadata, 'en');

        $this->assertSame(
            [
                'allOf' => [
                    [
                        'type' => ['number', 'string', 'boolean', 'object', 'array', 'null'],
                    ],
                    [
                        'anyOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'product_with_variants'],
                                ],
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'product'],
                                    // minLength, because a bare `required` would accept the `null`
                                    // that a code-less product loads with
                                    'code' => ['type' => 'string', 'minLength' => 1],
                                ],
                                'required' => ['code'],
                            ],
                        ],
                    ],
                ],
            ],
            $formMetadata->getSchema()->toJsonSchema(),
        );
    }
}
