<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DataFixtures\ORM\AttributeTypes;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;

class LoadAttributeTypes implements FixtureInterface, OrderedFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        // force id = 1
        $metadata = $manager->getClassMetaData(get_class(new AttributeType()));
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        $i = 1;
        $file = dirname(__FILE__) . '/../../attribute-types.xml';
        $doc = new \DOMDocument();
        $doc->load($file);

        $xpath = new \DOMXpath($doc);
        $elements = $xpath->query('/attribute-types/attribute-type');

        if (!is_null($elements)) {
            /** @var $element DOMNode */
            foreach ($elements as $element) {
                $attributeType = new AttributeType();
                $attributeType->setId($i);
                $children = $element->childNodes;
                /** @var $child DOMNode */
                foreach ($children as $child) {
                    if (isset($child->nodeName)) {
                        if ($child->nodeName == 'name') {
                            $attributeType->setName($child->nodeValue);
                        }
                    }
                }
                $manager->persist($attributeType);
                ++$i;
            }
        }
        $manager->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 1;
    }
}
