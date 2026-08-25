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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\Exception\InvalidProductDetailsFieldException;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductDetailsFieldMetadataValidator;

#[CoversClass(ProductDetailsFieldMetadataValidator::class)]
#[CoversClass(InvalidProductDetailsFieldException::class)]
class ProductDetailsFieldMetadataValidatorTest extends TestCase
{
    private ProductDetailsFieldMetadataValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProductDetailsFieldMetadataValidator();
    }

    /** @return \Generator<string, array{string}> */
    public static function reservedNames(): \Generator
    {
        foreach (['attributes', 'associations', 'variants', 'code', 'externalIdentifier', 'productFamily', 'status'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('reservedNames')]
    public function testReservedFieldNameThrows(string $name): void
    {
        $this->expectException(InvalidProductDetailsFieldException::class);
        $this->expectExceptionMessageMatches('/reserved/');

        $this->validator->validate(new FieldMetadata('details/' . $name), ProductInterface::FORM_KEY);
    }

    public function testUnreservedFieldNamePasses(): void
    {
        $this->validator->validate(new FieldMetadata('details/documents'), ProductInterface::FORM_KEY);

        $this->expectNotToPerformAssertions();
    }

    public function testFieldsOutsideTheDetailsPrefixArePassedOver(): void
    {
        $this->validator->validate(new FieldMetadata('attributes'), ProductInterface::FORM_KEY);

        $this->expectNotToPerformAssertions();
    }

    public function testOtherFormsArePassedOver(): void
    {
        $this->validator->validate(new FieldMetadata('details/attributes'), 'some_other_form');

        $this->expectNotToPerformAssertions();
    }

    public function testExceptionExposesThePropertyName(): void
    {
        $exception = new InvalidProductDetailsFieldException('details/attributes', ProductInterface::FORM_KEY, 'reserved');

        self::assertSame('details/attributes', $exception->getPropertyName());
    }
}
