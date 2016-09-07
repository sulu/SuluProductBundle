<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DataFixtures\ORM\Currencies;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Entity\Currency;

class LoadCurrencies implements FixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        // force id = 1
        $metadata = $manager->getClassMetaData(get_class(new Currency()));
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        $i = 1;
        $file = dirname(__FILE__) . '/../../currencies.xml';
        $doc = new \DOMDocument();
        $doc->load($file);

        $xpath = new \DOMXpath($doc);
        $elements = $xpath->query('/currencies/currency');

        if (!is_null($elements)) {
            /** @var $element DOMNode */
            foreach ($elements as $element) {
                $currency = new Currency();
                $currency->setId($i);
                $children = $element->childNodes;
                /** @var $child DOMNode */
                foreach ($children as $child) {
                    switch ($child->nodeName) {
                        case 'name':
                            $currency->setName($child->nodeValue);
                            break;
                        case 'code':
                            $currency->setCode($child->nodeValue);
                            break;
                        case 'number':
                            $currency->setNumber($child->nodeValue);
                            break;
                    }
                }
                $manager->persist($currency);
                ++$i;
            }
        }
        $manager->flush();
    }
}
