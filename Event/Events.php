<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Event;

/**
 * Container for product-events.
 */
final class Events
{
    /**
     * Indicates that a translation has been created via flush.
     */
    const PRODUCT_TRANSLATION_CREATED = 'sulu_product.translation.created';

    /**
     * Private constructor.
     */
    public function __construct()
    {
    }
}
