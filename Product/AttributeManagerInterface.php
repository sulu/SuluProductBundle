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
 * The interface for the attribute manager.
 */
interface AttributeManagerInterface
{
    /**
     * Returns the FieldDescriptors for the attributes.
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
     * Returns all attributes in the given locale.
     *
     * @param string $locale
     *
     * @return Attribute[]
     */
    public function findAllByLocale($locale);

    /**
     * Saves the given attribute.
     *
     * @param array $data The data for the attribute to save
     * @param string $locale The locale in which the attribute should be saved
     * @param int $userId The id of the user who called this action
     * @param int $id The id of the attribute, if the attribute is already saved in the database
     *
     * @return Attribute
     */
    public function save(array $data, $locale, $userId, $id = null);

    /**
     * Deletes the given attribute.
     *
     * @param int $id The id of the attribute to delete
     * @param int $userId The user who delete the attribute
     */
    public function delete($id, $userId);
}
