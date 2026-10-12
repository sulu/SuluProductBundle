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
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;

#[CoversClass(DateAttributeType::class)]
class DateAttributeTypeTest extends TestCase
{
    public function testKey(): void
    {
        $type = new DateAttributeType();
        self::assertSame(AttributeInterface::TYPE_DATE, $type->getKey());
    }

    public function testConfigureFieldLeavesTheFieldAlone(): void
    {
        $field = new FieldMetadata('value');

        (new DateAttributeType())->configureField($field, new Attribute(new AttributeGroup()), 'en');

        self::assertSame([], $field->getOptions());
    }
}
