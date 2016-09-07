<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Content\Types;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Component\Content\Compat\PropertyInterface;
use Sulu\Component\Content\SimpleContentType;

class ProductSelectionContentType extends SimpleContentType
{
    /**
     * @var string
     */
    protected $template;

    /**
     * @var ProductManagerInterface
     */
    protected $productManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @param string $template
     * @param ProductManagerInterface $productManager
     * @param SerializerInterface $serializer
     */
    public function __construct($template, ProductManagerInterface $productManager, SerializerInterface $serializer)
    {
        parent::__construct('Product', []);

        $this->template = $template;
        $this->productManager = $productManager;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentData(PropertyInterface $property)
    {
        $locale = $property->getStructure()->getLanguageCode();
        $value = $property->getValue();
        if ($value === null || !is_array($value) || count($value) === 0) {
            return [];
        }
        $entities = $this->productManager->createApiEntitiesByIds($value, $locale);
        $result = [];
        foreach ($entities as $entity) {
            $result[array_search($entity->getId(), $value, false)] = $this->serializer->serialize(
                $entity,
                'array',
                SerializationContext::create()->setSerializeNull(true)
            );
        }
        ksort($result);

        return array_values($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate()
    {
        return $this->template;
    }
}
