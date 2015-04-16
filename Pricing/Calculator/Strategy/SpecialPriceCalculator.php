<?php

namespace Sulu\Bundle\ProductBundle\Pricing\Strategy;

use Sulu\Bundle\ProductBundle\Pricing\Calculable\PriceableInterface;

class SpecialPriceCalculator implements PriceCalculatorInterface
{
    const TYPE = 'special_price';

    /**
     * {@inheritdoc}
     */
    public function calculate(PriceableInterface $subject, array $context)
    {
        // TODO: Implement calculate() method.
    }
}
