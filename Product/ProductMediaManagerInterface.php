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

use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;

/**
 * Interface for product media manager service, which is responsible for managing product media relations.
 */
interface ProductMediaManagerInterface
{
    /**
     * Returns the field descriptors for product media.
     *
     * @param string $locale
     *
     * @return DoctrineFieldDescriptor[]
     */
    public function getFieldDescriptors($locale);

    /**
     * Creates a relation between product and media for all given media ids.
     * All preexisting relations are going to get lost.
     *
     * @param ProductInterface $product
     * @param array $mediaIds
     */
    public function save(ProductInterface $product, array $mediaIds);

    /**
     * Deletes product media relations.
     *
     * @param ProductInterface $product
     * @param array $mediaIds
     */
    public function delete(ProductInterface $product, array $mediaIds);
}
