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

/**
 * This exception is thrown when an error occurs while mapping data to a product entity.
 */
class ProductMappingException extends ProductException
{
    /**
     * @var string
     */
    private $propertyName;

    /**
     * @var string
     */
    private $description;

    /**
     * @param string $propertyName
     * @param string $description
     */
    public function __construct($propertyName, $description = '')
    {
        $this->propertyName = $propertyName;
        $this->description = $description;

        parent::__construct(
            sprintf('An error occured while mapping property \'%s\' to product entity. %s', $propertyName, $description)
        );
    }
}
