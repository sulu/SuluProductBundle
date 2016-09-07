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

use Sulu\Bundle\ProductBundle\Api\Attribute;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;

/**
 * The interface for the attribute value manager.
 */
interface AttributeValueManagerInterface
{
    /**
     * Returns the FieldDescriptors for the attribute values.
     *
     * @param $locale
     *
     * @return DoctrineFieldDescriptor[]
     */
    public function getFieldDescriptors($locale);

    /**
     * Returns the attribute with the given ID and locale.
     *
     * @param int $id The id of the attribute to load
     * @param string $locale The locale to load
     *
     * @return Attribute
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Saves the given attribute value.
     *
     * @param array $data The data for the attribute value to save
     * @param string $locale The locale in which the attribute value should be saved
     * @param int $id The id of the attribute, if the attribute is already saved in the database
     *
     * @return Attribute
     */
    public function save(array $data, $locale, $id = null);

    /**
     * Deletes the given attribute value.
     *
     * @param int $id The id of the attribute to delete
     * @param int $userId The user who delete the attribute
     */
    public function delete($attributeValueId, $userId);
}
