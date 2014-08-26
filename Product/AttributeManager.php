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

use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;
use Doctrine\Common\Persistence\ObjectManager;

class AttributeManager implements AttributeManagerInterface
{
    protected static $attributeEntityName = 'SuluProductBundle:Attribute';
    protected static $attributeTranslationEntityName = 'SuluProductBundle:AttributeTranslation';

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        ObjectManager $em
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->em = $em;
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldDescriptors($locale)
    {
        $fieldDescriptors['title'] = new DoctrineFieldDescriptor(
            'title',
            'title',
            self::$attributeTranslationEntityName,
            'attribute.title',
            array(
                self::$attributeTranslationEntityName => new DoctrineJoinDescriptor(
                    self::$attributeTranslationEntityName,
                    self::$attributeEntityName . '.translations',
                    self::$attributeTranslationEntityName . '.locale = \'' . $locale . '\''
                )
            )
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
        $attribute = $this->attributeRepository->findByIdAndLocale($id, $locale);

        if ($attribute) {
            return new Attribute($attribute, $locale);
        } else {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByLocale($locale, $filter = array())
    {
        if (empty($filter)) {
            $attributes = $this->attributeRepository->findAllByLocale($locale);
        } else {
            $attributes = $this->attributeRepository->findByLocaleAndFilter($locale, $filter);
        }

        array_walk(
            $attributes,
            function (&$attribute) use ($locale) {
                $attribute = new Attribute($attribute, $locale);
            }
        );

        return $attributes;
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $data, $locale, $userId, $id = null)
    {
        // TODO: implement
    }

    /**
     * {@inheritDoc}
     */
    public function delete($id, $userId)
    {
        // TODO: implement
    }
}
