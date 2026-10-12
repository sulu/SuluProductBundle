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
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\RangeAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;

#[CoversClass(RangeAttributeType::class)]
class RangeAttributeTypeTest extends TestCase
{
    private function type(): RangeAttributeType
    {
        return new RangeAttributeType(new NumberAttributeType());
    }

    public function testKey(): void
    {
        self::assertSame(AttributeInterface::TYPE_RANGE, $this->type()->getKey());
    }

    public function testConfigureFieldAddsMinMaxStepFromConfig(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['min' => -20, 'max' => 100, 'step' => 0.5]);

        $field = new FieldMetadata('from');
        $this->type()->configureField($field, $attribute, 'en');

        $options = $field->getOptions();
        self::assertArrayHasKey('min', $options);
        self::assertArrayHasKey('max', $options);
        self::assertArrayHasKey('step', $options);
        self::assertSame('-20', $options['min']->getValue());
        self::assertSame('100', $options['max']->getValue());
        self::assertSame('0.5', $options['step']->getValue());
    }

    public function testConfigureFieldAddsNoOptionsWithoutConfig(): void
    {
        $field = new FieldMetadata('to');
        $this->type()->configureField($field, new Attribute(new AttributeGroup()), 'en');

        self::assertSame([], $field->getOptions());
    }

    public function testConfigureFieldSkipsMissingConfigKeys(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['max' => 60]);

        $field = new FieldMetadata('to');
        $this->type()->configureField($field, $attribute, 'en');

        $options = $field->getOptions();
        self::assertArrayHasKey('max', $options);
        self::assertArrayNotHasKey('min', $options);
        self::assertArrayNotHasKey('step', $options);
    }
}
