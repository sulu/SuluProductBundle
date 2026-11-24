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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductMainCategoryFormMetadataVisitor;

#[CoversClass(ProductMainCategoryFormMetadataVisitor::class)]
class ProductMainCategoryFormMetadataVisitorTest extends TestCase
{
    public function testLimitsTheSelectionToTheRootKey(): void
    {
        [$form, $field] = $this->createForm('product_details');

        (new ProductMainCategoryFormMetadataVisitor('shop'))->visitFormMetadata($form, 'en');

        $requestParameters = $field->getOptions()['request_parameters']->getValue();
        $this->assertIsArray($requestParameters);
        $this->assertCount(1, $requestParameters);
        $this->assertSame('rootKey', $requestParameters[0]->getName());
        $this->assertSame('shop', $requestParameters[0]->getValue());
    }

    public function testChangesNothingWithoutARootKey(): void
    {
        [$form, $field] = $this->createForm('product_details');

        (new ProductMainCategoryFormMetadataVisitor(null))->visitFormMetadata($form, 'en');

        $this->assertSame([], $field->getOptions());
    }

    public function testIgnoresOtherForms(): void
    {
        [$form, $field] = $this->createForm('product_variant');

        (new ProductMainCategoryFormMetadataVisitor('shop'))->visitFormMetadata($form, 'en');

        $this->assertSame([], $field->getOptions());
    }

    public function testIgnoresAFormWithoutTheField(): void
    {
        $form = new FormMetadata();
        $form->setKey('product_details');

        (new ProductMainCategoryFormMetadataVisitor('shop'))->visitFormMetadata($form, 'en');

        $this->assertSame([], $form->getItems());
    }

    /**
     * @return array{FormMetadata, FieldMetadata}
     */
    private function createForm(string $key): array
    {
        $field = new FieldMetadata('mainCategory');
        $field->setType('single_category_selection');

        $form = new FormMetadata();
        $form->setKey($key);
        $form->addItem($field);

        return [$form, $field];
    }
}
