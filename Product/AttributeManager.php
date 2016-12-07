<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Api\Attribute;
use Sulu\Bundle\ProductBundle\Entity\Attribute as AttributeEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeTypeRepository;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\MissingAttributeException;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;

/**
 * Manager responsible for attributes.
 */
class AttributeManager implements AttributeManagerInterface
{
    protected static $attributeEntityName = 'SuluProductBundle:Attribute';
    protected static $attributeTranslationEntityName = 'SuluProductBundle:AttributeTranslation';
    protected static $attributeTypeEntityName = 'SuluProductBundle:AttributeType';

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var AttributeTypeRepository
     */
    private $attributeTypeRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var ProductLocaleManager
     */
    private $productLocaleManger;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param UserRepositoryInterface $userRepository
     * @param AttributeTypeRepository $attributeTypeRepository
     * @param ObjectManager $em
     * @param ProductLocaleManager $productLocaleManager
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        UserRepositoryInterface $userRepository,
        AttributeTypeRepository $attributeTypeRepository,
        ObjectManager $em,
        ProductLocaleManager $productLocaleManager
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->userRepository = $userRepository;
        $this->attributeTypeRepository = $attributeTypeRepository;
        $this->em = $em;
        $this->productLocaleManger = $productLocaleManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDescriptors($locale)
    {
        $fieldDescriptors = [];

        $fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            self::$attributeEntityName,
            'public.id',
            [],
            true
        );

        $fieldDescriptors['name'] = new DoctrineFieldDescriptor(
            'name',
            'name',
            self::$attributeTranslationEntityName,
            'product.attribute.name',
            [
                self::$attributeTranslationEntityName => new DoctrineJoinDescriptor(
                    self::$attributeTranslationEntityName,
                    self::$attributeEntityName . '.translations',
                    self::$attributeTranslationEntityName . '.locale = \'' . $locale . '\''
                ),
            ]
        );

        $fieldDescriptors['type'] = new DoctrineFieldDescriptor(
            'name',
            'type',
            self::$attributeTypeEntityName,
            'product.attribute.type',
            [
                self::$attributeTypeEntityName => new DoctrineJoinDescriptor(
                    self::$attributeTypeEntityName,
                    self::$attributeEntityName . '.type'
                ),
            ],
            false,
            true,
            'translation'
        );

        $fieldDescriptors['key'] = new DoctrineFieldDescriptor(
            'key',
            'key',
            self::$attributeEntityName,
            'product.attribute.key',
            [],
            false,
            true
        );

        return $fieldDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        $attribute = $this->attributeRepository->findByIdAndLocale($id, $locale);

        if ($attribute) {
            return new Attribute($attribute, $locale, $this->productLocaleManger->getFallbackLocale());
        } else {
            throw new AttributeNotFoundException($id);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAllByLocale($locale)
    {
        $attributes = $this->attributeRepository->findAllByLocale($locale);

        array_walk(
            $attributes,
            function (&$attribute) use ($locale) {
                $attribute = new Attribute($attribute, $locale, $this->productLocaleManger->getFallbackLocale());
            }
        );

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function findAllByLocaleAndType($locale, $type)
    {
        $attributes = $this->attributeRepository->findAllByLocaleAndType($locale, $type);

        array_walk(
            $attributes,
            function (&$attribute) use ($locale) {
                $attribute = new Attribute($attribute, $locale, $this->productLocaleManger->getFallbackLocale());
            }
        );

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $data, $locale, $userId, $id = null)
    {
        if ($id) {
            $attribute = $this->attributeRepository->findById($id);
            if (!$attribute) {
                throw new AttributeNotFoundException($id);
            }
            $attribute = new Attribute($attribute, $locale, $this->productLocaleManger->getFallbackLocale());
        } else {
            $attribute = new Attribute(new AttributeEntity(), $locale, $this->productLocaleManger->getFallbackLocale());
        }

        $this->checkData($data, $id === null);

        $user = $this->userRepository->findUserById($userId);

        $attribute->setChanged(new DateTime());
        $attribute->setChanger($user);
        $attribute->setName($this->getProperty($data, 'name', $attribute->getName()));
        $attribute->setKey($this->getProperty($data, 'key', $attribute->getKey()));

        if (array_key_exists('type', $data) && array_key_exists('id', $data['type'])) {
            $typeId = $data['type']['id'];
            /** @var Type $type */
            $type = $this->attributeTypeRepository->find($typeId);
            if (!$type) {
                throw new AttributeDependencyNotFoundException(self::$attributeTypeEntityName, $typeId);
            }
            $attribute->setType($type);
        }

        if (array_key_exists('type', $data) && array_key_exists('id', $data['type'])) {
            $typeId = $data['type']['id'];
            /** @var Type $type */
            $type = $this->attributeTypeRepository->find($typeId);
            if (!$type) {
                throw new AttributeDependencyNotFoundException(self::$attributeTypeEntityName, $typeId);
            }
            $attribute->setType($type);
        }

        if ($attribute->getId() == null) {
            $attribute->setCreated(new DateTime());
            $attribute->setCreator($user);
            $this->em->persist($attribute->getEntity());
        }

        $this->em->flush();

        return $attribute;
    }

    /**
     * Returns the entry from the data with the given key, or the given default value,
     * if the key does not exist.
     *
     * @param array $data
     * @param string $key
     * @param string $default
     *
     * @return mixed
     */
    private function getProperty(array $data, $key, $default = null)
    {
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    private function checkData($data, $create)
    {
        $this->checkDataSet($data, 'type', $create) && $this->checkDataSet($data['type'], 'id', $create);
        $this->checkDataSet($data, 'name', $create);
    }

    private function checkDataSet(array $data, $key, $create)
    {
        $keyExists = array_key_exists($key, $data);

        if (($create && !($keyExists && $data[$key] !== null))) {
            throw new MissingAttributeException($key);
        }

        return $keyExists;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id, $userId)
    {
        $attribute = $this->attributeRepository->findById($id);

        if (!$attribute) {
            throw new AttributeNotFoundException($id);
        }

        $this->em->remove($attribute);
        $this->em->flush();
    }
}
