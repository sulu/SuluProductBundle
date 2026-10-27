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

namespace Sulu\Product\Domain\Value;

enum ProductFamilyAttributeModeEnum: string
{
    /** One value on the product, shared by all variants. */
    case SHARED = 'shared';

    /** One value per variant, none on the product. */
    case VARIANT_SPECIFIC = 'variant_specific';

    /** The product holds the default, a variant may override it. */
    case OVERRIDABLE = 'overridable';
}
