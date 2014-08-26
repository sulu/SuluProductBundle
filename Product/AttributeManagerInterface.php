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

use Sulu\Bundle\ProductBundle\Api\Attribute;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;

/**
 * The interface for the attribute manager
 * @package Sulu\Bundle\ProductBundle\Product
 */
interface AttributeManagerInterface
{
    /**
     * Returns the FieldDescriptors for the attributes
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
     * Returns the attribute with the given ID and locale
     * @param int $id The id of the attribute to load
     * @param string $locale The locale to load
     * @return Attribute
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Returns all attributes in the given locale
     * @param string $locale
     * @param array $filter
     * @return Attribute[]
     */
    public function findAllByLocale($locale, $filter = array());

    /**
     * Saves the given attribute
     * @param array $data The data for the attribute to save
     * @param string $locale The locale in which the attribute should be saved
     * @param integer $userId The id of the user who called this action
     * @param integer $id The id of the attribute, if the attribute is already saved in the database
     * @return Attribute
     */
    public function save(array $data, $locale, $userId, $id = null);

    /**
     * Deletes the given attribute
     * @param integer $id The id of the attribute to delete
     * @param int $userId The user who delete the attribute
     */
    public function delete($id, $userId);
}
