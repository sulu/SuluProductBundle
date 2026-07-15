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

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductStatusFormMetadataVisitor;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductStatusFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    public function testInjectsConfiguredStatusOptions(): void
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(\Prophecy\Argument::type('string'), [], 'admin', 'en')
            ->willReturn('Label');

        $statusField = new FieldMetadata('status');
        $statusField->setType('single_select');

        $form = new FormMetadata();
        $form->setKey('product_details');
        $form->addItem($statusField);

        $visitor = new ProductStatusFormMetadataVisitor(['announced', 'available'], $translator->reveal());
        $visitor->visitFormMetadata($form, 'en', []);

        $options = $statusField->getOptions();
        self::assertArrayHasKey('values', $options);

        $valueOptions = $options['values']->getValue();
        self::assertIsArray($valueOptions);
        self::assertCount(2, $valueOptions);
    }

    public function testIgnoresOtherForms(): void
    {
        $form = new FormMetadata();
        $form->setKey('some_other_form');

        $visitor = new ProductStatusFormMetadataVisitor(['announced'], $this->prophesize(TranslatorInterface::class)->reveal());

        // Must not throw when the status field is absent.
        $visitor->visitFormMetadata($form, 'en', []);
        self::assertSame([], $form->getItems());
    }

    public function testIgnoresProductDetailsFormWithoutStatusField(): void
    {
        // The product_details form exists but has no `status` field yet (e.g. a
        // consumer that removed it): the visitor must no-op, not blow up.
        $otherField = new FieldMetadata('someOtherField');

        $form = new FormMetadata();
        $form->setKey('product_details');
        $form->addItem($otherField);

        $visitor = new ProductStatusFormMetadataVisitor(['announced'], $this->prophesize(TranslatorInterface::class)->reveal());
        $visitor->visitFormMetadata($form, 'en', []);

        self::assertSame([], $otherField->getOptions());
    }
}
