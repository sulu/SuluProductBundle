<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;

/**
 * The interface for the product manager
 * @package Sulu\Bundle\ProductBundle\Product
 */
interface ProductManagerInterface
{
    /**
     * Returns the FieldDescriptors for the products
     * @param $locale
     * @return DoctrineFieldDescriptor[]
     */
    public function getFieldDescriptors($locale);

    /**
     * Returns the FieldDescriptor for the given key
     * @param string $key The key of the FieldDescriptor to return
     * @return DoctrineFieldDescriptor
     */
    public function getFieldDescriptor($key);

    /**
     * Returns the product with the given ID and locale
     * @param int $id The id of the product to load
     * @param string $locale The locale to load
     * @return Product
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Returns all products in the given locale
     * @param string $locale
     * @param array $filter
     * @return Product[]
     */
    public function findAllByLocale($locale, $filter = array());

    /**
     * Saves the given product
     * @param array $data The data for the product to save
     * @param string $locale The locale in which the product should be saved
     * @param integer $userId The id of the user who called this action
     * @param integer $id The id of the product, if the product is already saved in the database
     * @return Product
     */
    public function save(array $data, $locale, $userId, $id = null);

    /**
     * Deletes the given product
     * @param integer $id The id of the product to delete
     * @param int $userId The user who delete the product
     */
    public function delete($id, $userId);
} 
