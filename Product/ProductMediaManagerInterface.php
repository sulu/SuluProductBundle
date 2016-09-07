<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;

interface ProductMediaManagerInterface
{
    /**
     * Returns the field descriptors for product media.
     *
     * @return DoctrineFieldDescriptor[]
     */
    public function getFieldDescriptors();
}
