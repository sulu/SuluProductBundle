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

namespace Sulu\Product\Tests\Unit\Support;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;

/**
 * @internal
 */
final class FormOf
{
    public static function fields(FieldMetadata ...$fields): FormMetadata
    {
        $form = new FormMetadata();
        foreach ($fields as $field) {
            $form->addItem($field);
        }

        return $form;
    }
}
