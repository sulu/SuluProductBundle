<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * CountryTax
 */
class CountryTax
{
    /**
     * @var float
     */
    private $tax;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\TaxClass
     */
    private $taxClass;

    /**
     * @var \Sulu\Bundle\ContactBundle\Entity\Country
     */
    private $country;


    /**
     * Set tax
     *
     * @param float $tax
     * @return CountryTax
     */
    public function setTax($tax)
    {
        $this->tax = $tax;

        return $this;
    }

    /**
     * Get tax
     *
     * @return float 
     */
    public function getTax()
    {
        return $this->tax;
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set taxClass
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass
     * @return CountryTax
     */
    public function setTaxClass(\Sulu\Bundle\ProductBundle\Entity\TaxClass $taxClass = null)
    {
        $this->taxClass = $taxClass;

        return $this;
    }

    /**
     * Get taxClass
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\TaxClass 
     */
    public function getTaxClass()
    {
        return $this->taxClass;
    }

    /**
     * Set country
     *
     * @param \Sulu\Bundle\ContactBundle\Entity\Country $country
     * @return CountryTax
     */
    public function setCountry(\Sulu\Bundle\ContactBundle\Entity\Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country
     *
     * @return \Sulu\Bundle\ContactBundle\Entity\Country 
     */
    public function getCountry()
    {
        return $this->country;
    }
}
