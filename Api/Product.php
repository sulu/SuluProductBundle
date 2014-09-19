<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Api;

use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface as Entity;
use JMS\Serializer\Annotation\VirtualProperty;
use JMS\Serializer\Annotation\SerializedName;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Component\Rest\ApiWrapper;
use Sulu\Component\Security\UserInterface;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet as AttributeSetEntity;
use Sulu\Bundle\ProductBundle\Entity\Status as StatusEntity;
use Sulu\Bundle\ProductBundle\Entity\Type as TypeEntity;
use Hateoas\Configuration\Annotation\Relation;

/**
 * The product class which will be exported to the API
 * @package Sulu\Bundle\ProductBundle\Api
 * @Relation("self", href="expr('/api/admin/products/' ~ object.getId())")
 */
class Product extends ApiWrapper
{
    /**
     * @param Entity $product The product to wrap
     * @param string $locale The locale of this product
     */
    public function __construct(Entity $product, $locale) {
        $this->entity = $product;
        $this->locale = $locale;
    }

    /**
     * Returns the id of the product
     * @return int
     * @VirtualProperty
     * @SerializedName("id")
     */
    public function getId()
    {
        return $this->entity->getId();
    }

    /**
     * Returns the name of the product
     * @return string The name of the product
     * @VirtualProperty
     * @SerializedName("name")
     */
    public function getName()
    {
        return $this->getTranslation()->getName();
    }

    /**
     * Sets the name of the product
     * @param string $name The name of the product
     */
    public function setName($name)
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * Returns the short description of the product
     * @return string The short description of the product
     * @VirtualProperty
     * @SerializedName("shortDescription")
     */
    public function getShortDescription()
    {
        return $this->getTranslation()->getShortDescription();
    }

    /**
     * Sets the short description of the product
     * @param string $shortDescription The short description of the product
     */
    public function setShortDescription($shortDescription)
    {
        $this->getTranslation()->setShortDescription($shortDescription);
    }

    /**
     * Returns the long description of the product
     * @return string The long description of the product
     * @VirtualProperty
     * @SerializedName("longDescription")
     */
    public function getLongDescription()
    {
        return $this->getTranslation()->getLongDescription();
    }

    /**
     * Sets the long description of the product
     * @param string $longDescription The short description of the product
     */
    public function setLongDescription($longDescription)
    {
        $this->getTranslation()->setLongDescription($longDescription);
    }

    /**
     * Returns the code of the product
     * @return string The code of the product
     * @VirtualProperty
     * @SerializedName("code")
     */
    public function getCode()
    {
        return $this->entity->getCode();
    }

    /**
     * Sets the code of the product
     * @param string $code The code of the product
     */
    public function setCode($code)
    {
        $this->entity->setCode($code);
    }

    /**
     * Returns the number of the product
     * @return string The number of the product
     * @VirtualProperty
     * @SerializedName("number")
     */
    public function getNumber()
    {
        return $this->entity->getNumber();
    }

    /**
     * Sets the number of the product
     * @param string $number The number of the product
     */
    public function setNumber($number)
    {
        $this->entity->setNumber($number);
    }

    /**
     * Returns the cost of the product
     * @return double The cost of the product
     * @VirtualProperty
     * @SerializedName("cost")
     */
    public function getCost()
    {
        return $this->entity->getCost();
    }

    /**
     * Sets the cost of the product
     * @param double $cost The cost of the product
     */
    public function setCost($cost)
    {
        $this->entity->setCost($cost);
    }

    /**
     * Sets the priceinfo of the product
     * @param string $priceInfo The cost of the product
     */
    public function setPriceInfo($priceInfo)
    {
        $this->entity->setPriceInfo($priceInfo);
    }

    /**
     * Returns the cost of the product
     * @return double The cost of the product
     * @VirtualProperty
     * @SerializedName("priceInfo")
     */
    public function getPriceInfo()
    {
        return $this->entity->getPriceInfo();
    }

    /**
     * Returns the manufacturer of the product
     * @return string The manufacturer of the product
     * @VirtualProperty
     * @SerializedName("manufacturer")
     */
    public function getManufacturer()
    {
        return $this->entity->getManufacturer();
    }

    /**
     * Sets the manufacturer of the product
     * @param string $manufacturer The manufacturer of the product
     */
    public function setManufacturer($manufacturer)
    {
        $this->entity->setManufacturer($manufacturer);
    }

    /**
     * Returns the parent of the product
     * @return Product The parent of the product
     * @VirtualProperty
     * @SerializedName("parent")
     */
    public function getParent()
    {
        $parent = $this->entity->getParent();

        if ($parent) {
            return new Product($parent, $this->locale);
        } else {
            return null;
        }
    }

    /**
     * Sets the parent of the product
     * @param Product $parent The parent of the product
     */
    public function setParent(Product $parent = null)
    {
        if ($parent != null) {
            $this->entity->setParent($parent->getEntity());
        } else {
            $this->entity->setParent(null);
        }
    }

    /**
     * Returns the children of the product
     * @return ProductInterface[]
     */
    public function getChildren()
    {
        return $this->entity->getChildren();
    }

    /**
     * Adds a product attribute to the product
     * @param ProductAttribute $productAttribute
     */
    public function addProductAttribute(ProductAttribute $productAttribute)
    {
        $this->entity->addProductAttribute($productAttribute);
    }

    /**
     * Returns the type of the product
     * @return Type The type of the product
     * @VirtualProperty
     * @SerializedName("type")
     */
    public function getType()
    {
        return new Type($this->entity->getType(), $this->locale);
    }

    /**
     * Sets the type of the product
     * @param TypeEntity $type The type of the product
     */
    public function setType(TypeEntity $type)
    {
        $this->entity->setType($type);
    }

    /**
     * Returns the status of the product
     * @return Status The status of the product
     * @VirtualProperty
     * @SerializedName("status")
     */
    public function getStatus()
    {
        return new Status($this->entity->getStatus(), $this->locale);
    }

    /**
     * Sets the status of the product
     * @param StatusEntity $status The status of the product
     */
    public function setStatus(StatusEntity $status)
    {
        $this->entity->setStatus($status);
    }

    /**
     * Returns the attribute set of the product
     * @return AttributeSet The attribute set of the product
     * @VirtualProperty
     * @SerializedName("attributeSet")
     */
    public function getAttributeSet()
    {
        $attributeSet = $this->entity->getAttributeSet();
        if ($attributeSet) {
            return new AttributeSet($attributeSet, $this->locale);
        } else {
            return null;
        }
    }

    /**
     * Sets the attribute set of the product
     * @param AttributeSetEntity $attributeSet The attribute set of the product
     */
    public function setAttributeSet(AttributeSetEntity $attributeSet)
    {
        $this->entity->setAttributeSet($attributeSet);
    }

    /**
     * Sets the changer of the product
     * @param UserInterface $user The changer of the product
     */
    public function setChanger(UserInterface $user)
    {
        $this->entity->setChanger($user);
    }

    /**
     * Sets the creator of the product
     * @param UserInterface $user The creator of the product
     */
    public function setCreator(UserInterface $user)
    {
        $this->entity->setCreator($user);
    }

    /**
     * Returns the creator of the product
     * @return creator/owner of the product
     */
    public function getCreator()
    {
        return $this->entity->getCreator();
    }

    /**
     * Sets the change time of the product
     * @param \DateTime $changed
     */
    public function setChanged(\DateTime $changed)
    {
        $this->entity->setChanged($changed);
    }

    /**
     * Sets the created time of the product
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created)
    {
        $this->entity->setCreated($created);
    }

    private function getTranslation()
    {
        $productTranslation = $this->entity->getTranslation($this->locale);
        if (!$productTranslation) {
            $productTranslation = new ProductTranslation();
            $productTranslation->setLocale($this->locale);
            $productTranslation->setProduct($this->entity);

            $this->entity->addTranslation($productTranslation);
        }
        return $productTranslation;
    }
}
