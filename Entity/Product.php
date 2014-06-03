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
use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\SecurityBundle\Entity\User;

/**
 * Product
 */
abstract class Product implements ProductInterface
{
    /**
     * @var integer
     */
    private $id;

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
     * @var Country
     */
    private $manufacturerCountry;

    /**
     * @var Type
     */
    private $type;

    /**
     * @var Template
     */
    private $template;

    /**
     * @var Status
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
     * @var User
     */
    private $changer;

    /**
     * @var User
     */
    private $creator;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\Product
     */
    private $parent;

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
     * @param Country $manufacturerCountry
     * @return Product
     */
    public function setManufacturerCountry(Country $manufacturerCountry = null)
    {
        $this->manufacturerCountry = $manufacturerCountry;
    
        return $this;
    }

    /**
     * Get manufacturerCountry
     *
     * @return Country
     */
    public function getManufacturerCountry()
    {
        return $this->manufacturerCountry;
    }

    /**
     * Set type
     *
     * @param Type $type
     * @return Product
     */
    public function setType(Type $type)
    {
        $this->type = $type;
    
        return $this;
    }

    /**
     * Get type
     *
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set template
     *
     * @param Template $template
     * @return Product
     */
    public function setTemplate(Template $template)
    {
        $this->template = $template;
    
        return $this;
    }

    /**
     * Get template
     *
     * @return Template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set status
     *
     * @param Status $status
     * @return Product
     */
    public function setStatus(Status $status = null)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return Status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Add sets
     *
     * @param Set $sets
     * @return Product
     */
    public function addSet(Set $sets)
    {
        $this->sets[] = $sets;
    
        return $this;
    }

    /**
     * Remove sets
     *
     * @param Set $sets
     */
    public function removeSet(Set $sets)
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
     * @param ProductInterface $relations
     * @return Product
     */
    public function addRelation(ProductInterface $relations)
    {
        $this->relations[] = $relations;
    
        return $this;
    }

    /**
     * Remove relations
     *
     * @param ProductInterface $relations
     */
    public function removeRelation(ProductInterface $relations)
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
     * @param ProductInterface $upsells
     * @return Product
     */
    public function addUpsell(ProductInterface $upsells)
    {
        $this->upsells[] = $upsells;
    
        return $this;
    }

    /**
     * Remove upsells
     *
     * @param ProductInterface $upsells
     */
    public function removeUpsell(ProductInterface $upsells)
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
     * @param ProductInterface $crosssells
     * @return Product
     */
    public function addCrosssell(ProductInterface $crosssells)
    {
        $this->crosssells[] = $crosssells;
    
        return $this;
    }

    /**
     * Remove crosssells
     *
     * @param ProductInterface $crosssells
     */
    public function removeCrosssell(ProductInterface $crosssells)
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
     * @param User $changer
     * @return Product
     */
    public function setChanger(User $changer = null)
    {
        $this->changer = $changer;
    
        return $this;
    }

    /**
     * Get changer
     *
     * @return User
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * Set creator
     *
     * @param User $creator
     * @return Product
     */
    public function setCreator(User $creator = null)
    {
        $this->creator = $creator;
    
        return $this;
    }

    /**
     * Get creator
     *
     * @return User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set parent
     *
     * @param ProductInterface $parent
     * @return Product
     */
    public function setParent(ProductInterface $parent = null)
    {
        $this->parent = $parent;
    
        return $this;
    }

    /**
     * Get parent
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\Product 
     */
    public function getParent()
    {
        return $this->parent;
    }
}
