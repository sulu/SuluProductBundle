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
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeAttributeException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeValueNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeValueAttributeException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeValueDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\AttributeValueManagerInterface;

class AttributeValueManager implements AttributeValueManagerInterface
{
    protected static $attributeValueEntityName = 'SuluProductBundle:AttributeValue';
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
            array(),
            true
        );
        return $fieldDescriptors;
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldDescriptor($key)
    {
        return $this->fieldDescriptors[$key];
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
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $data, $locale, $attributeId = null, $attributeValueId = null)
    {
        if ($attributeValueId) {
            $attributeValue = $this->attributeValueRepository->findByIdAndLocale($attributeValueId, $locale);
            if (!$attributeValue) {
                throw new AttributeNotFoundException($attributeValueid);
            }
            $attributeValue = new AttributeValue($attributeValue, $locale);
        } else {
            $attributeValue = new AttributeValue(new AttributeValueEntity(), $locale);
        }

        $attributeValue->setName($this->getProperty($data, 'name', $attributeValue->getName()));
        if ($this->getProperty($data, 'selected')) {
            $attributeValue->setSelected($this->getProperty($data, 'selected', $attributeValue->getSelected()));
        }

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
    public function delete($id, $userId)
    {
        $attributeValue = $this->attributeValueRepository->findById($id);

        if (!$attributeValue) {
            throw new AttributeValueNotFoundException($id);
        }

        $this->em->remove($attributeValue);
        $this->em->flush();
    }
}
