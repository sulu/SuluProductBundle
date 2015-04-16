<?php

namespace Sulu\Bundle\ProductBundle\Pricing\Strategy;

use Sulu\Bundle\ProductBundle\Pricing\Calculable\PriceableInterface;

interface PriceCalculatorInterface
{
    /**
     * @param PriceableInterface $subject
     * @param array $context
     * @return mixed
     */
    public function calculate(PriceableInterface $subject, array $context);
}
