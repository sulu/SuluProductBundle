<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;

class CountryTax
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var float
     */
    private $tax;

    /**
     * @var TaxClass
     */
    private $taxClass;

    /**
     * @var Country
     */
    private $country;

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param float $tax
     *
     * @return self
     */
    public function setTax($tax)
    {
        $this->tax = $tax;

        return $this;
    }

    /**
     * @return float
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * @param TaxClass $taxClass
     *
     * @return self
     */
    public function setTaxClass(TaxClass $taxClass = null)
    {
        $this->taxClass = $taxClass;

        return $this;
    }

    /**
     * @return TaxClass
     */
    public function getTaxClass()
    {
        return $this->taxClass;
    }

    /**
     * @param Country $country
     *
     * @return self
     */
    public function setCountry(Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return Country
     */
    public function getCountry()
    {
        return $this->country;
    }
}
