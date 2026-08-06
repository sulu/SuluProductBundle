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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class ProductVariantFormTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    /**
     * mapDetailsData() resolves `details/` fields against the product_details metadata, for
     * variants too. A field named differently here matches nothing and is dropped on save.
     */
    public function testVariantDetailFieldsAreDeclaredInProductDetailsToo(): void
    {
        self::bootKernel();

        /** @var FormMetadataProvider $provider */
        $provider = self::getContainer()->get('sulu_admin.form_metadata_provider');

        $variantForm = $provider->getMetadata('product_variant', 'en');
        $this->assertInstanceOf(FormMetadata::class, $variantForm);
        $productForm = $provider->getMetadata('product_details', 'en');
        $this->assertInstanceOf(FormMetadata::class, $productForm);

        $variantDetailFields = $this->detailFieldNames($variantForm);
        $this->assertNotSame([], $variantDetailFields, 'Expected the variant form to declare detail fields.');

        $productDetailFields = $this->detailFieldNames($productForm);

        foreach ($variantDetailFields as $name) {
            $this->assertContains(
                $name,
                $productDetailFields,
                \sprintf('Variant field "%s" is not declared in product_details and would never be persisted.', $name),
            );
        }
    }

    public function testVariantFormCarriesTheSameDetailFieldsAsTheProduct(): void
    {
        self::bootKernel();

        /** @var FormMetadataProvider $provider */
        $provider = self::getContainer()->get('sulu_admin.form_metadata_provider');

        $variantForm = $provider->getMetadata('product_variant', 'en');
        $this->assertInstanceOf(FormMetadata::class, $variantForm);

        $this->assertSame(
            ['details/documents', 'details/image', 'details/shortDescription'],
            $this->detailFieldNames($variantForm),
        );
    }

    /**
     * @return array<int, string>
     */
    private function detailFieldNames(FormMetadata $form): array
    {
        $names = [];
        foreach (\array_keys($form->getFlatFieldMetadata()) as $name) {
            if (\str_starts_with($name, 'details/')) {
                $names[] = $name;
            }
        }

        \sort($names);

        return $names;
    }
}
