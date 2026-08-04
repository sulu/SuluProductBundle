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
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Infrastructure\Sulu\Admin\Exception\InvalidProductAssociationFieldException;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAssociationsFieldMetadataValidator;

#[CoversClass(ProductAssociationsFieldMetadataValidator::class)]
#[CoversClass(InvalidProductAssociationFieldException::class)]
final class ProductAssociationsFieldMetadataValidatorTest extends TestCase
{
    private ProductAssociationsFieldMetadataValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProductAssociationsFieldMetadataValidator(
            new ProductAssociationTypeRegistry([
                'alternative' => ['label' => 'Alternative'],
                'suitable' => ['label' => 'Suitable'],
            ]),
        );
    }

    private function field(string $name, string $type): FieldMetadata
    {
        $field = new FieldMetadata($name);
        $field->setType($type);

        return $field;
    }

    public function testAcceptsWellFormedField(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate($this->field('associations/alternative', 'product_selection'), 'product_associations');
    }

    public function testIgnoresOtherFormKeys(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate($this->field('anything', 'text_line'), 'page');
    }

    public function testThrowsOnFieldNameWithoutPrefix(): void
    {
        $this->expectException(InvalidProductAssociationFieldException::class);
        $this->expectExceptionMessage('must be named "associations/<type>"');

        $this->validator->validate($this->field('alternative', 'product_selection'), 'product_associations');
    }

    public function testThrowsOnUnknownAssociationType(): void
    {
        $this->expectException(InvalidProductAssociationFieldException::class);
        $this->expectExceptionMessage('unknown association type "nonexistent", configured types: "alternative", "suitable"');

        $this->validator->validate($this->field('associations/nonexistent', 'product_selection'), 'product_associations');
    }

    public function testThrowsOnUnsupportedFieldType(): void
    {
        $this->expectException(InvalidProductAssociationFieldException::class);
        $this->expectExceptionMessage('field type "text_line" is not supported');

        $this->validator->validate($this->field('associations/alternative', 'text_line'), 'product_associations');
    }

    public function testExceptionExposesPropertyNameAndFormKey(): void
    {
        try {
            $this->validator->validate($this->field('alternative', 'product_selection'), 'product_associations');
            self::fail('Expected InvalidProductAssociationFieldException');
        } catch (InvalidProductAssociationFieldException $exception) {
            self::assertSame('alternative', $exception->getPropertyName());
            self::assertSame('product_associations', $exception->getFormKey());
        }
    }
}
