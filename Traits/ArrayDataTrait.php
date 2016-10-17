<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Traits;

/**
 * This Trait provides basic convenience helper functions.
 */
trait ArrayDataTrait
{
    /**
     * Returns the value for a given key or if not existent
     * the given default value.
     *
     * @param array $data
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getProperty(array $data, $key, $default = null)
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        return $data[$key];
    }
}
