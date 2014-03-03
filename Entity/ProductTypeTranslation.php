<?php

namespace Sulu\Bundle\Product\BaseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductTypeTranslation
 */
class ProductTypeTranslation
{
    /**
     * @var integer
     */
    private $id;


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
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $languageCode;

    /**
     * @var \Sulu\Bundle\Product\BaseBundle\Entity\ProductType
     */
    private $productType;


    /**
     * Set name
     *
     * @param string $name
     * @return ProductTypeTranslation
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
     * Set languageCode
     *
     * @param string $languageCode
     * @return ProductTypeTranslation
     */
    public function setLanguageCode($languageCode)
    {
        $this->languageCode = $languageCode;
    
        return $this;
    }

    /**
     * Get languageCode
     *
     * @return string 
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * Set productType
     *
     * @param \Sulu\Bundle\Product\BaseBundle\Entity\ProductType $productType
     * @return ProductTypeTranslation
     */
    public function setProductType(\Sulu\Bundle\Product\BaseBundle\Entity\ProductType $productType)
    {
        $this->productType = $productType;
    
        return $this;
    }

    /**
     * Get productType
     *
     * @return \Sulu\Bundle\Product\BaseBundle\Entity\ProductType 
     */
    public function getProductType()
    {
        return $this->productType;
    }
}