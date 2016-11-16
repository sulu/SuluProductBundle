<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Preview;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\PreviewBundle\Preview\Object\PreviewObjectProviderInterface;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductRepositoryInterface;

/**
 * Integrates products into preview-system.
 */
class ProductPreviewObjectProvider implements PreviewObjectProviderInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductManagerInterface
     */
    private $productManager;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductManagerInterface $productManager
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductManagerInterface $productManager,
        SerializerInterface $serializer
    ) {
        $this->productRepository = $productRepository;
        $this->productManager = $productManager;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getObject($id, $locale)
    {
        $product = $this->productRepository->find($id);

        return $this->productManager->retrieveOrCreateProductTranslationByLocale($product, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getId($object)
    {
        return $object->getProduct()->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function setValues($object, $locale, array $data)
    {
//        $propertyAccess = PropertyAccess::createPropertyAccessorBuilder()
//            ->enableMagicCall()
//            ->getPropertyAccessor();
//
//        $structure = $object->getStructure();
//        foreach ($data as $property => $value) {
//            try {
//                $propertyAccess->setValue($structure, $property, $value);
//            } catch (\InvalidArgumentException $e) {
//                //ignore not existing properties
//            }
//        }

        // TODO: ???
    }

    /**
     * {@inheritdoc}
     */
    public function setContext($object, $locale, array $context)
    {
//        if (array_key_exists('template', $context)) {
//            $object->setStructureType($context['template']);
//        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize($object)
    {
        return $this->serializer->serialize(
            $object,
            'json',
            SerializationContext::create()
                ->setSerializeNull(true)
                ->enableMaxDepthChecks()
                ->setGroups(['preview'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize($serializedObject, $objectClass)
    {
        $translation =  $this->serializer->deserialize(
            $serializedObject,
            $objectClass,
            'json',
            DeserializationContext::create()
                ->setSerializeNull(true)
                ->setGroups(['preview'])
        );
        // Add translation itself, since it was not serialized (avoid circular serialization)
        $translation->getProduct()->addTranslation($translation);

        return $translation;
    }
}
