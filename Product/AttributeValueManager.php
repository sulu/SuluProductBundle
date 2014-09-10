<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use \DateTime;

use Doctrine\Common\Persistence\ObjectManager;

use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;
use Sulu\Component\Security\UserRepositoryInterface;
use Sulu\Bundle\ProductBundle\Entity\AttributeTypeRepository;
use Sulu\Bundle\ProductBundle\Api\Attribute;
use Sulu\Bundle\ProductBundle\Entity\Attribute as AttributeEntity;
use Sulu\Bundle\ProductBundle\Api\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeValueNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeValueException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeValueDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\AttributeValueManagerInterface;

/**
 * Manager responsible for attributeValues
 * @package Sulu\Bundle\ProductBundle\Product
 */
class AttributeValueManager implements AttributeValueManagerInterface
{
    protected static $attributeValueEntityName = 'SuluProductBundle:AttributeValue';
    protected static $attributeEntityName = 'SuluProductBundle:Attribute';
    protected static $attributeValueTranslationEntityName = 'SuluProductBundle:AttributeValueTranslation';
    protected static $attributeRepository = 'SuluProductBundle:Attribute';

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeValueRepository;

    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(
        AttributeValueRepositoryInterface $attributeValueRepository,
        UserRepositoryInterface $userRepository,
        ObjectManager $em
    ) {
        $this->attributeValueRepository = $attributeValueRepository;
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldDescriptors($locale)
    {
        $fieldDescriptors = array();

        $fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            self::$attributeValueEntityName,
            'public.id',
            array(),
            true
        );

        $fieldDescriptors['name'] = new DoctrineFieldDescriptor(
            'name',
            'name',
            self::$attributeValueTranslationEntityName,
            'product.attribute.value.name',
            array(
                self::$attributeValueTranslationEntityName => new DoctrineJoinDescriptor(
                    self::$attributeValueTranslationEntityName,
                    self::$attributeValueEntityName . '.translations',
                    self::$attributeValueTranslationEntityName . '.locale = \'' . $locale . '\''
                )
            )
        );

        $fieldDescriptors['selected'] = new DoctrineFieldDescriptor(
            'selected',
            'selected',
            self::$attributeValueEntityName,
            'product.attribute.value.selected',
            array()
        );

        $fieldDescriptors['attribute_id'] = new DoctrineFieldDescriptor(
            'id',
            'attribute_id',
            self::$attributeEntityName,
            null,
            array(
                self::$attributeEntityName => new DoctrineJoinDescriptor(
                    self::$attributeEntityName,
                    self::$attributeValueEntityName . '.attribute'
                )
            ),
            true
        );

        return $fieldDescriptors;
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        $attributeValue = $this->attributeValueRepository->findByIdAndLocale($id, $locale);

        if ($attributeValue) {
            return new AttributeValue($attributeValue, $locale);
        } else {
            throw new AttributeValueNotFoundException($id);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByAttributeIdAndLocale($locale, $id)
    {
        $attributeValues = $this->attributeValueRepository->findByAttributeIdAndLocale($id, $locale);
        if ($attributeValues) {
            array_walk(
                $attributeValues,
                function (&$attributeValue) use ($locale) {
                    $attributeValue = new AttributeValue($attributeValue, $locale);
                }
            );
            return $attributeValues;
        } else {
            throw new AttributeNotFoundException($id);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByLocale($locale)
    {
        $attributeValues = $this->attributeValueRepository->findAllByLocale($locale);

        array_walk(
            $attributeValues,
            function (&$attributeValue) use ($locale) {
                $attributeValue = new AttributeValue($attributeValue, $locale);
            }
        );

        return $attributeValues;
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $data, $locale, $attributeId = null, $attributeValueId = null)
    {
        if ($attributeValueId) {
            $attributeValue = $this->attributeValueRepository->findByIdAndLocale($attributeValueId, $locale);
            if (!$attributeValue) {
                throw new AttributeValueNotFoundException($attributeValueId);
            }
            $attributeValue = new AttributeValue($attributeValue, $locale);
        } else {
            $attributeValue = new AttributeValue(new AttributeValueEntity(), $locale);
        }

        $this->checkData($data, $attributeValueId === null);

        $attributeValue->setName($this->getProperty($data, 'name', $attributeValue->getName()));
        $attributeValue->setSelected($this->getProperty($data, 'selected', $attributeValue->getSelected()));

        if ($attributeValue->getId() == null) {
            $this->em->persist($attributeValue->getEntity());
        }

        if ($attributeId) {
            $attribute = $this->em->getRepository(self::$attributeRepository)->find($attributeId);
            $attributeValue->setAttribute($attribute);
        }

        $this->em->flush();
        return $attributeValue;
    }

    private function checkData($data, $create)
    {
        $this->checkDataSet($data, 'name', $create);
    }

    private function checkDataSet(array $data, $key, $create)
    {
        $keyExists = array_key_exists($key, $data);

        if (($create && !($keyExists && $data[$key] !== null))) {
            throw new MissingAttributeValueException($key);
        }

        return $keyExists;
    }

    /**
     * Returns the entry from the data with the given key, or the given default value,
     * if the key does not exist
     * @param array $data
     * @param string $key
     * @param string $default
     * @return mixed
     */
    private function getProperty(array $data, $key, $default = null)
    {
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($attributeValueId, $userId)
    {
        $attributeValue = $this->attributeValueRepository->findById($attributeValueId);

        if (!$attributeValue) {
            throw new AttributeValueNotFoundException($attributeValueId);
        }

        $this->em->remove($attributeValue);
        $this->em->flush();
    }
}
