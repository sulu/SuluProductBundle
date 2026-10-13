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
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\BooleanAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Tests\Unit\Fixture\LocaleKeyTranslator;
use Sulu\Product\Tests\Unit\Support\FormOf;

#[CoversClass(BooleanAttributeType::class)]
class BooleanAttributeTypeTest extends TestCase
{
    public function testKey(): void
    {
        $type = $this->type();
        self::assertSame(AttributeInterface::TYPE_BOOLEAN, $type->getKey());
    }

    public function testConfigureFieldOffersOnlyYesAndNoWhenRequired(): void
    {
        $field = new FieldMetadata('attribute_1');
        $field->setRequired(true);

        $this->type()->configureForm(FormOf::fields($field), new Attribute(new AttributeGroup()), 'de');

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

        $this->type()->configureForm(FormOf::fields($field), new Attribute(new AttributeGroup()), 'de');

        $options = $field->getOptions()['values']->getValue();
        self::assertIsArray($options);
        self::assertSame(['', 'true', 'false'], \array_map(static fn (OptionMetadata $option) => $option->getName(), $options));
        self::assertNull($options[0]->getValue());
        self::assertSame(
            ['de:sulu_product.not_specified', 'de:sulu_admin.yes', 'de:sulu_admin.no'],
            \array_map(static fn (OptionMetadata $option) => $option->getTitle('de'), $options),
        );
    }

    private function type(): BooleanAttributeType
    {
        return new BooleanAttributeType(new LocaleKeyTranslator());
    }
}
