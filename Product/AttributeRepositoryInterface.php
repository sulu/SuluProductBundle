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

use Sulu\Bundle\ProductBundle\Entity\AttributeInterface;
use Sulu\Component\Persistence\Repository\RepositoryInterface;

/**
 * The interface for the AttributeRepository.
 */
interface AttributeRepositoryInterface extends RepositoryInterface
{
    /**
     * Finds the attribute with the given ID.
     *
     * @param int $id The id of the attribute
     *
     * @return AttributeInterface
     */
    public function findById($id);

    /**
     * Finds the attribute with the given ID in the given language.
     *
     * @param int $id The id of the attribute
     * @param string $locale The locale of the attribute to load
     *
     * @return AttributeInterface
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Returns all attributes in the given locale.
     *
     * @param string $locale The locale of the attribute to load
     *
     * @return AttributeInterface[]
     */
    public function findAllByLocale($locale);
}
