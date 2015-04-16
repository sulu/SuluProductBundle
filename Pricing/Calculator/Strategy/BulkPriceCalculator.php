<?php

namespace Sulu\Bundle\ProductBundle\Pricing\Strategy;

use Sulu\Bundle\ProductBundle\Pricing\Calculable\PriceableInterface;

class BulkPriceCalculator implements PriceCalculatorInterface
{
    const TYPE = 'bulk_price';

    /**
     * {@inheritdoc}
     */
    public function calculate(PriceableInterface $subject, array $context)
    {
        // TODO: Implement calculate() method.
    }
}
