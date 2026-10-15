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
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\BooleanAttributeType;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Application\AttributeType\FormattableAttributeTypeInterface;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeDisplayFormatFormMetadataVisitor;
use Sulu\Product\Tests\Unit\Fixture\LocaleKeyTranslator;
use Sulu\Product\Tests\Unit\Support\AttributeTypes;

#[CoversClass(AttributeDisplayFormatFormMetadataVisitor::class)]
class AttributeDisplayFormatFormMetadataVisitorTest extends TestCase
{
    private function form(string $key = 'attribute_details'): FormMetadata
    {
        $form = new FormMetadata();
        $form->setKey($key);
        $form->addItem(new FieldMetadata('config/displayFormat'));

        return $form;
    }

    private function projectType(): AttributeTypeInterface
    {
        return new class() implements AttributeTypeInterface, FormattableAttributeTypeInterface {
            public function getKey(): string
            {
                return 'größe/"x"';
            }

            public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
            {
            }

            public function formatValue(AttributeInterface $attribute, mixed $value, string $locale): ?string
            {
                return null;
            }

            public function hasDisplayFormat(): bool
            {
                return true;
            }
        };
    }

    public function testShowsTheFieldForTheTypesWithADisplayFormat(): void
    {
        $form = $this->form();

        $visitor = new AttributeDisplayFormatFormMetadataVisitor([
            AttributeTypes::number(),
            new OptionsAttributeType(),
            new DateAttributeType(),
            new BooleanAttributeType(new LocaleKeyTranslator()),
            $this->projectType(),
        ]);
        $visitor->visitFormMetadata($form, 'en');

        self::assertSame(
            'type == "number" || type == "date" || type == "größe/\"x\""',
            $form->getItems()['config/displayFormat']->getVisibleCondition(),
        );
    }

    public function testHidesTheFieldWhenNoTypeHasADisplayFormat(): void
    {
        $form = $this->form();

        (new AttributeDisplayFormatFormMetadataVisitor([new OptionsAttributeType()]))->visitFormMetadata($form, 'en');

        self::assertSame('false', $form->getItems()['config/displayFormat']->getVisibleCondition());
    }

    public function testLeavesOtherFormsAlone(): void
    {
        $form = $this->form('product_details');

        (new AttributeDisplayFormatFormMetadataVisitor([AttributeTypes::number()]))->visitFormMetadata($form, 'en');

        self::assertNull($form->getItems()['config/displayFormat']->getVisibleCondition());
    }

    public function testLeavesAFormWithoutTheFieldAlone(): void
    {
        $form = new FormMetadata();
        $form->setKey('attribute_details');
        $form->addItem(new SectionMetadata('config/displayFormat'));

        (new AttributeDisplayFormatFormMetadataVisitor([AttributeTypes::number()]))->visitFormMetadata($form, 'en');

        self::assertNull($form->getItems()['config/displayFormat']->getVisibleCondition());
    }
}
