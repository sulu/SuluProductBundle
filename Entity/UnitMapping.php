<?php

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UnitMapping
 */
class UnitMapping
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ProductBundle\Entity\Unit
     */
    private $unit;


    /**
     * Set name
     *
     * @param string $name
     * @return UnitMapping
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
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
     * Set unit
     *
     * @param \Sulu\Bundle\ProductBundle\Entity\Unit $unit
     * @return UnitMapping
     */
    public function setUnit(\Sulu\Bundle\ProductBundle\Entity\Unit $unit)
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * Get unit
     *
     * @return \Sulu\Bundle\ProductBundle\Entity\Unit 
     */
    public function getUnit()
    {
        return $this->unit;
    }
}
