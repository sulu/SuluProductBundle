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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductManagerInterface $productManager
     * @param SerializerInterface $serializer
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductManagerInterface $productManager,
        SerializerInterface $serializer,
        TokenStorageInterface $tokenStorage
    ) {
        $this->productRepository = $productRepository;
        $this->productManager = $productManager;
        $this->serializer = $serializer;
        $this->tokenStorage = $tokenStorage;
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
        // TODO: Implement.
    }

    /**
     * {@inheritdoc}
     */
    public function setContext($object, $locale, array $context)
    {
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
        $translation = $this->serializer->deserialize(
            $serializedObject,
            $objectClass,
            'json',
            DeserializationContext::create()
                ->setSerializeNull(true)
                ->setGroups(['preview'])
        );
        // Add translation itself, since it was not serialized to avoid circular serialization.
        $translation->getProduct()->addTranslation($translation);

        return $translation;
    }
}
