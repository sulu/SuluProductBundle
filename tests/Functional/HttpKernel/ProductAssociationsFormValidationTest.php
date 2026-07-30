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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\Loader\FormXmlLoader;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\Validation\FieldMetadataValidatorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\XmlFormMetadataLoader;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Sulu\Admin\Exception\InvalidProductAssociationFieldException;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAssociationsFieldMetadataValidator;

/**
 * Proves the validator is really wired into "sulu_admin.field_metadata_validator":
 * an invalid project form must fail through the real XmlFormMetadataLoader warmup,
 * not just in a unit call.
 */
#[CoversClass(ProductAssociationsFieldMetadataValidator::class)]
final class ProductAssociationsFormValidationTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testInvalidProjectFormFailsCacheWarmup(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        /** @var FormXmlLoader $formXmlLoader */
        $formXmlLoader = $container->get('sulu_admin.form_metadata.form_xml_loader');
        /** @var FieldMetadataValidatorInterface $chainValidator */
        $chainValidator = $container->get('sulu_admin.field_metadata_validator.chain');

        $cacheDir = \sys_get_temp_dir() . '/sulu-product-invalid-forms-' . \uniqid('', true);
        $loader = new XmlFormMetadataLoader(
            $formXmlLoader,
            $chainValidator,
            [__DIR__ . '/fixtures/invalid-forms'],
            $cacheDir,
            true,
        );

        $this->expectException(InvalidProductAssociationFieldException::class);
        $this->expectExceptionMessage('unknown association type "nonexistent"');

        $loader->warmUp($cacheDir);
    }
}
