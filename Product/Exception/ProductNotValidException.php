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

class ProductNotValidException extends ProductException
{
    /**
     * @var string
     */
    private $entityName;

    /**
     * @var int
     */
    private $id;

    /**
     * @param string $id
     * @param string $entityName
     */
    public function __construct($id, $entityName = 'SuluProductBundle:Product')
    {
        $this->id = $id;
        $this->entityName = $entityName;

        parent::__construct('The product with the id "' . $this->id . '" is not valid.', 0);
    }

    /**
     * Returns the name of the entity.
     *
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * Returns the id of the entity.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
