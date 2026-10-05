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

namespace Sulu\Product\Tests\Unit\Application\AttributeType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\BooleanAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Tests\Unit\Fixture\LocaleKeyTranslator;

#[CoversClass(BooleanAttributeType::class)]
class BooleanAttributeTypeTest extends TestCase
{
    public function testKeyAndFormKey(): void
    {
        $type = $this->type();
        self::assertSame(AttributeInterface::TYPE_BOOLEAN, $type->getKey());
        self::assertSame('product_attribute_boolean', $type->getFormKey());
    }

    public function testConfigureFieldOffersOnlyYesAndNoWhenRequired(): void
    {
        $field = new FieldMetadata('attribute_1');
        $field->setRequired(true);

        $this->type()->configureField($field, new Attribute(new AttributeGroup()), 'de');

        $values = $field->getOptions()['values'];
        self::assertSame(OptionMetadata::TYPE_COLLECTION, $values->getType());

        $options = $values->getValue();
        self::assertIsArray($options);
        self::assertSame(['true', 'false'], \array_map(static fn (OptionMetadata $option) => $option->getName(), $options));
        self::assertSame(['de:sulu_admin.yes', 'de:sulu_admin.no'], \array_map(static fn (OptionMetadata $option) => $option->getTitle('de'), $options));
    }

    public function testConfigureFieldOffersEmptyChoiceWhenOptional(): void
    {
        $field = new FieldMetadata('attribute_1');
        $field->setRequired(false);

        $this->type()->configureField($field, new Attribute(new AttributeGroup()), 'de');

        $options = $field->getOptions()['values']->getValue();
        self::assertIsArray($options);
        self::assertSame(['', 'true', 'false'], \array_map(static fn (OptionMetadata $option) => $option->getName(), $options));
        self::assertNull($options[0]->getValue());
        self::assertSame(
            ['de:sulu_admin.please_choose', 'de:sulu_admin.yes', 'de:sulu_admin.no'],
            \array_map(static fn (OptionMetadata $option) => $option->getTitle('de'), $options),
        );
    }

    /**
     * @return iterable<string, array{mixed, float, string}>
     */
    public static function provideWrittenValues(): iterable
    {
        yield 'string true' => ['true', 1.0, 'true'];
        yield 'string false' => ['false', 0.0, 'false'];
        yield 'bool true' => [true, 1.0, 'true'];
        yield 'bool false' => [false, 0.0, 'false'];
    }

    #[DataProvider('provideWrittenValues')]
    public function testValueRoundTripUsesNumberColumn(mixed $raw, float $stored, string $read): void
    {
        $type = $this->type();
        $value = $this->value();

        $type->writeValue($value, $raw);

        self::assertSame($stored, $value->getNumber());
        self::assertSame($read, $type->readValue($value));
    }

    public function testFalseIsAValueNotEmpty(): void
    {
        $value = $this->value();

        $this->type()->writeValue($value, 'false');

        self::assertNotNull($value->getValue());
    }

    public function testWriteNullClearsNumber(): void
    {
        $type = $this->type();
        $value = $this->value();
        $type->writeValue($value, 'true');

        $type->writeValue($value, null);

        self::assertNull($value->getNumber());
        self::assertNull($type->readValue($value));
    }

    public function testWriteEmptyStringClearsNumber(): void
    {
        $type = $this->type();
        $value = $this->value();
        $type->writeValue($value, 'false');

        $type->writeValue($value, '');

        self::assertNull($value->getNumber());
    }

    public function testWriteUnknownStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->type()->writeValue($this->value(), 'yes');
    }

    public function testWriteNumberThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->type()->writeValue($this->value(), 1);
    }

    private function type(): BooleanAttributeType
    {
        return new BooleanAttributeType(new LocaleKeyTranslator());
    }

    private function value(): ProductAttributeValueInterface
    {
        return new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');
    }
}
