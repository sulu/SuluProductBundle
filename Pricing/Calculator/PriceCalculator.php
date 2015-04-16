<?php

namespace Sulu\Bundle\ProductBundle\Pricing;

use Sulu\Bundle\ProductBundle\Pricing\Calculable\PriceableInterface;

class PriceCalculator
{
    public function calculate(PriceableInterface $product, $context)
    {
        // TODO delegate price calculation and return class (ProductPriceCalculationResult)
    }
}
