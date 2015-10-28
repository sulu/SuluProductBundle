<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product\Exception;

class ProductNotActiveException extends ProductException
{
    /**
     * The name of the entity.
     *
     * @var string
     */
    private $entityName;

    /**
     * The id of the object not found.
     *
     * @var integer
     */
    private $id;

    /**
     * @param string $id
     * @param string $entityName
     */
    public function __construct($id, $entityName = 'SuluProductBundle:Product')
    {
        $this->entityName = $entityName;
        $this->id = $id;
        parent::__construct('The product with the id "' . $this->id . '" is not active.', 0);
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
