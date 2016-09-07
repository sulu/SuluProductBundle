<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DataFixtures\ORM\Units;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Entity\Unit;
use Sulu\Bundle\ProductBundle\Entity\UnitMapping;
use Sulu\Bundle\ProductBundle\Entity\UnitTranslation;

class LoadUnits implements FixtureInterface, OrderedFixtureInterface
{
    private static $translations = ['de', 'de_ch', 'en'];

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        // force id = 1
        $metadata = $manager->getClassMetaData(get_class(new Unit()));
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        $i = 1;
        $file = dirname(__FILE__) . '/../../units.xml';
        $doc = new \DOMDocument();
        $doc->load($file);

        $xpath = new \DOMXpath($doc);
        $elements = $xpath->query('/units/unit');

        if (!is_null($elements)) {
            /** @var $element DOMNode */
            foreach ($elements as $element) {
                $unit = new Unit();
                $unit->setId($i);
                $children = $element->childNodes;
                /** @var $child DOMNode */
                foreach ($children as $child) {
                    if (isset($child->nodeName) && $child->nodeName == 'translations') {
                        foreach ($child->childNodes as $child) {
                            if (isset($child->nodeName) && (in_array($child->nodeName, self::$translations))) {
                                $translation = new UnitTranslation();
                                $translation->setLocale($child->nodeName);
                                $translation->setName($child->nodeValue);
                                $translation->setUnit($unit);
                                $unit->addTranslation($translation);
                                $manager->persist($translation);
                            }
                        }
                    } elseif (isset($child->nodeName) && $child->nodeName == 'mappings') {
                        foreach ($child->childNodes as $child) {
                            if (isset($child->nodeName) && $child->nodeName === 'value') {
                                $mapping = new UnitMapping();
                                $mapping->setName($child->nodeValue);
                                $mapping->setUnit($unit);
                                $manager->persist($mapping);
                            }
                        }
                    }
                }
                $manager->persist($unit);
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
