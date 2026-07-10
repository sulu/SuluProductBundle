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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductContentFormMetadataVisitor;

#[CoversClass(ProductContentFormMetadataVisitor::class)]
class ProductContentFormMetadataVisitorTest extends TestCase
{
    private ProductContentFormMetadataVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ProductContentFormMetadataVisitor();
    }

    public function testIgnoresWrongKey(): void
    {
        $formMetadata = new TypedFormMetadata();
        $form = new FormMetadata();
        $titleField = new FieldMetadata('title');
        $form->setItems(['title' => $titleField]);
        $formMetadata->addForm('default', $form);

        $this->visitor->visitTypedFormMetadata($formMetadata, 'some_other_key', 'en');

        $this->assertNull($titleField->getDisabledCondition());
    }

    public function testDisablesTitleForProductTemplateType(): void
    {
        $formMetadata = new TypedFormMetadata();
        $form = new FormMetadata();
        $titleField = new FieldMetadata('title');
        $form->setItems(['title' => $titleField]);
        $formMetadata->addForm('default', $form);

        $this->visitor->visitTypedFormMetadata($formMetadata, ProductDimensionContent::getTemplateType(), 'en');

        $this->assertSame('true', $titleField->getDisabledCondition());
    }

    public function testSkipsFormWithoutTitleField(): void
    {
        $formMetadata = new TypedFormMetadata();
        $form = new FormMetadata();
        $form->setItems([]);
        $formMetadata->addForm('default', $form);

        $this->visitor->visitTypedFormMetadata($formMetadata, ProductDimensionContent::getTemplateType(), 'en');

        $this->addToAssertionCount(1);
    }

    public function testSkipsNonFieldTitleItem(): void
    {
        $formMetadata = new TypedFormMetadata();
        $form = new FormMetadata();
        $sectionTitle = new SectionMetadata('title');
        $form->setItems(['title' => $sectionTitle]);
        $formMetadata->addForm('default', $form);

        $this->visitor->visitTypedFormMetadata($formMetadata, ProductDimensionContent::getTemplateType(), 'en');

        $this->assertNull($sectionTitle->getDisabledCondition());
    }
}
