<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Product
 */
class Product
{
    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $number;

    /**
     * @var string
     */
    private $manufacturer;

    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $changed;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Sulu\Bundle\ContactBundle\Entity\Country
     */
    private $manufacturerCountry;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Type
     */
    private $type;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Template
     */
    private $template;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Status
     */
    private $status;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $sets;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $relations;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $upsells;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $crosssells;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    private $changer;

    /**
     * @var \Sulu\Bundle\SecurityBundle\Entity\User
     */
    private $creator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->sets = new \Doctrine\Common\Collections\ArrayCollection();
        $this->relations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->upsells = new \Doctrine\Common\Collections\ArrayCollection();
        $this->crosssells = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Set code
     *
     * @param string $code
     * @return Product
     */
    public function setCode($code)
    {
        $this->code = $code;
    
        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set number
     *
     * @param string $number
     * @return Product
     */
    public function setNumber($number)
    {
        $this->number = $number;
    
        return $this;
    }

    /**
     * Get number
     *
     * @return string 
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set manufacturer
     *
     * @param string $manufacturer
     * @return Product
     */
    public function setManufacturer($manufacturer)
    {
        $this->manufacturer = $manufacturer;
    
        return $this;
    }

    /**
     * Get manufacturer
     *
     * @return string 
     */
    public function getManufacturer()
    {
        return $this->manufacturer;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Product
     */
    public function setCreated($created)
    {
        $this->created = $created;
    
        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set changed
     *
     * @param \DateTime $changed
     * @return Product
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;
    
        return $this;
    }

    /**
     * Get changed
     *
     * @return \DateTime 
     */
    public function getChanged()
    {
        return $this->changed;
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
     * Set manufacturerCountry
     *
     * @param \Sulu\Bundle\ContactBundle\Entity\Country $manufacturerCountry
     * @return Product
     */
    public function setManufacturerCountry(\Sulu\Bundle\ContactBundle\Entity\Country $manufacturerCountry = null)
    {
        $this->manufacturerCountry = $manufacturerCountry;
    
        return $this;
    }

    /**
     * Get manufacturerCountry
     *
     * @return \Sulu\Bundle\ContactBundle\Entity\Country 
     */
    public function getManufacturerCountry()
    {
        return $this->manufacturerCountry;
    }

    /**
     * Set type
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Type $type
     * @return Product
     */
    public function setType(\Sulu\Bundle\Product\BaseBundle\Entity\Type $type)
    {
        $this->type = $type;
    
        return $this;
    }

    /**
     * Get type
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Type 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set template
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Template $template
     * @return Product
     */
    public function setTemplate(\Sulu\Bundle\Product\BaseBundle\Entity\Template $template)
    {
        $this->template = $template;
    
        return $this;
    }

    /**
     * Get template
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Template 
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set status
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Status $status
     * @return Product
     */
    public function setStatus(\Sulu\Bundle\Product\BaseBundle\Entity\Status $status = null)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Status 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Add sets
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Set $sets
     * @return Product
     */
    public function addSet(\Sulu\Bundle\Product\BaseBundle\Entity\Set $sets)
    {
        $this->sets[] = $sets;
    
        return $this;
    }

    /**
     * Remove sets
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Set $sets
     */
    public function removeSet(\Sulu\Bundle\Product\BaseBundle\Entity\Set $sets)
    {
        $this->sets->removeElement($sets);
    }

    /**
     * Get sets
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSets()
    {
        return $this->sets;
    }

    /**
     * Add relations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $relations
     * @return Product
     */
    public function addRelation(\Sulu\Bundle\Product\BaseBundle\Entity\Product $relations)
    {
        $this->relations[] = $relations;
    
        return $this;
    }

    /**
     * Remove relations
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $relations
     */
    public function removeRelation(\Sulu\Bundle\Product\BaseBundle\Entity\Product $relations)
    {
        $this->relations->removeElement($relations);
    }

    /**
     * Get relations
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Add upsells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells
     * @return Product
     */
    public function addUpsell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells)
    {
        $this->upsells[] = $upsells;
    
        return $this;
    }

    /**
     * Remove upsells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells
     */
    public function removeUpsell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $upsells)
    {
        $this->upsells->removeElement($upsells);
    }

    /**
     * Get upsells
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUpsells()
    {
        return $this->upsells;
    }

    /**
     * Add crosssells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells
     * @return Product
     */
    public function addCrosssell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells)
    {
        $this->crosssells[] = $crosssells;
    
        return $this;
    }

    /**
     * Remove crosssells
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells
     */
    public function removeCrosssell(\Sulu\Bundle\Product\BaseBundle\Entity\Product $crosssells)
    {
        $this->crosssells->removeElement($crosssells);
    }

    /**
     * Get crosssells
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getCrosssells()
    {
        return $this->crosssells;
    }

    /**
     * Set changer
     *
     * @param \Sulu\Bundle\SecurityBundle\Entity\User $changer
     * @return Product
     */
    public function setChanger(\Sulu\Bundle\SecurityBundle\Entity\User $changer = null)
    {
        $this->changer = $changer;
    
        return $this;
    }

    /**
     * Get changer
     *
     * @return \Sulu\Bundle\SecurityBundle\Entity\User 
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator
     *
     * @param \Sulu\Bundle\SecurityBundle\Entity\User $creator
     * @return Product
     */
    public function setCreator(\Sulu\Bundle\SecurityBundle\Entity\User $creator = null)
    {
        $this->creator = $creator;
    
        return $this;
    }

    /**
     * Get creator
     *
     * @return \Sulu\Bundle\SecurityBundle\Entity\User 
     */
    public function getCreator()
    {
        return $this->creator;
    }
}
