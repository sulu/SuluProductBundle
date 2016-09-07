<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product\Exception;

class ProductChildrenExistException extends ProductException
{
    /**
     * The id of the object not found.
     *
     * @var int
     */
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
        parent::__construct('The product with the id "' . $this->id . '" was not found.', 0);
    }

    /**
     * Returns the id of the object not found.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
