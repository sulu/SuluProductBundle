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

class InvalidProductAttributeException extends ProductException
{
    /**
     * The name of the attribute not found.
     *
     * @var string
     */
    private $attributeName;

    /**
     * The name of the attribute not found.
     *
     * @var mixed
     */
    private $attributeValue;

    public function __construct($attributeName, $attributeValue)
    {
        $this->attributeName = $attributeName;
        $this->attributeValue = $attributeValue;
        parent::__construct('The value "' . $this->attributeValue . '" is not valid for product attribute "' .
            $this->attributeName . '".', 0);
    }

    /**
     * Returns the name of the attribute not found.
     *
     * @return int
     */
    public function getAttributeName()
    {
        return $this->attributeName;
    }

    /**
     * Returns the name of the attribute not found.
     *
     * @return int
     */
    public function getAttributeValue()
    {
        return $this->attributeValue;
    }
}
