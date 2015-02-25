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
     * Returns all products in the given locale and one of the ids
    * @param string $locale
    * @param array $ids
    * @return Product[]
    */
    public function findAllByIdsAndLocale($locale, $ids);

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
     * Updates the given data of a existing product
     * @param array $data The data for the product to save
     * @param string $locale The locale in which the product should be saved
     * @param integer $userId The id of the user who called this action
     * @param integer $id The id of the product, if the product is already saved in the database
     * @return Product
     * @throws Exception\ProductNotFoundException
     */
    public function partialUpdate(array $data, $locale, $userId, $id);

    /**
     * Adds a variant to a specific product
     * @param integer $parentId The id of the product, to which the variant is added
     * @param integer $variantId The id of the product, which is added to the other as a variant
     * @param string $locale The locale to load
     * @return Product The new variant
     */
    public function addVariant($parentId, $variantId, $locale);

    /**
     * Removes a variant from a specific product
     * @param integer $parentId The id of the product, from which the variant is removed
     * @param integer $variantId The id of the product, which is removed from the other
     */
    public function removeVariant($parentId, $variantId);

    /**
     * Deletes the given product
     * @param integer $id The id of the product to delete
     * @param bool $userId ID of the user that deletes the product
     * @param bool $flush Defines if a flush should be performed
     */
    public function delete($id, $userId= null, $flush = true);
} 
