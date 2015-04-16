<?php
namespace Sulu\Bundle\ProductBundle\Pricing\Calculable;

interface PriceableInterface {

    /**
     * @param string $type
     * @return mixed
     */
    public function getPricesByType($type);
}
