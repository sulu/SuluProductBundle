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

class ProductNotFoundException extends ProductException
{
    /**
     * The name of the object not found.
     *
     * @var string
     */
    private $entityName;

    /**
     * The id of the object not found.
     *
     * @var int
     */
    private $id;

    /**
     * @param int $id
     */
    public function __construct($id)
    {
        $this->entityName = 'SuluProductBundle:Product';
        $this->id = $id;
        parent::__construct(
            sprintf('Entity with the type "%s" and the id "%s" not found.', $this->entityName, $id),
            0
        );
    }

    /**
     * Returns the name of the entityname of the dependency not found.
     *
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
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
