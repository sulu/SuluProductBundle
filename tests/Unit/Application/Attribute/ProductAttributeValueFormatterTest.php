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

namespace Sulu\Product\Tests\Unit\Application\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Tests\Unit\Support\AttributeTypes;

#[CoversClass(ProductAttributeValueFormatter::class)]
class ProductAttributeValueFormatterTest extends TestCase
{
    private function attribute(string $type): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('attribute');
        $attribute->setType($type);

        return $attribute;
    }

    public function testAFormattableTypeFormatsItsValue(): void
    {
        $formatter = AttributeTypes::formatter();

        $number = $this->attribute(AttributeInterface::TYPE_NUMBER);
        $number->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);

        self::assertSame('16 mm', $formatter->format($number, 16.0, 'en'));
        self::assertTrue($formatter->format($this->attribute(AttributeInterface::TYPE_BOOLEAN), 'true', 'en'));
        self::assertSame('01.05.2024', $formatter->format($this->attribute(AttributeInterface::TYPE_DATE), '2024-05-01', 'de'));
        self::assertSame('≥ 5', $formatter->format($this->attribute(AttributeInterface::TYPE_RANGE), ['from' => 5.0], 'en'));
    }

    public function testATypeThatDoesNotFormatIsShownAsPlainText(): void
    {
        $type = new class() implements AttributeTypeInterface {
            public function getKey(): string
            {
                return 'dimensions';
            }

            public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
            {
            }
        };
        $formatter = new ProductAttributeValueFormatter(new AttributeTypeRegistry([$type]));
        $attribute = $this->attribute('dimensions');
        $attribute->setConfig(['displayFormat' => '%width% × %height%']);

        self::assertSame(
            '120, 80.5, boxed',
            $formatter->format($attribute, ['width' => 120, 'height' => 80.5, 'depth' => null, 'note' => 'boxed', 'tags' => ['a'], 'empty' => ''], 'en'),
        );
        self::assertSame('4.5', $formatter->format($attribute, 4.5, 'en'));
    }

    public function testAnUnregisteredTypeIsShownAsPlainText(): void
    {
        $formatter = AttributeTypes::formatter();
        $attribute = $this->attribute('custom');

        self::assertSame('true', $formatter->format($attribute, 'true', 'en'));
        self::assertSame('16', $formatter->format($attribute, 16, 'en'));
        self::assertSame('red, blue', $formatter->format($attribute, ['red', 'blue'], 'en'));
    }

    public function testPlainTextOfNothingIsNull(): void
    {
        $formatter = AttributeTypes::formatter();
        $attribute = $this->attribute('custom');

        self::assertNull($formatter->format($attribute, null, 'en'));
        self::assertNull($formatter->format($attribute, '', 'en'));
        self::assertNull($formatter->format($attribute, true, 'en'));
        self::assertNull($formatter->format($attribute, [], 'en'));
        self::assertNull($formatter->format($attribute, ['width' => null, 'note' => ''], 'en'));
    }
}
