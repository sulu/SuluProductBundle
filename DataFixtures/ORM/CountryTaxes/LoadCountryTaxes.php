<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Entity\CountryTax;

class LoadCountryTaxes implements FixtureInterface, OrderedFixtureInterface
{
    // Adapt these if there are any changes in database
    private $mappings = array(
        'tax-classes' => array(
            'standard' => 1,
            'reduced' => 2,
        ),
    );

//    private static $translations = ['de', 'en'];
    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        // force id = 1
        $metadata = $manager->getClassMetaData(get_class(new CountryTax()));
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

//        $i = 1;
//        $file = dirname(__FILE__) . '/../../country-taxes.xml';
//        $doc = new DOMDocument();
//        $doc->load($file);
//
//        $xpath = new DOMXpath($doc);
//        $elements = $xpath->query('/tax-classes/tax-class');
//
//        if (!is_null($elements)) {
//            /** @var $element DOMNode */
//            foreach ($elements as $element) {
//                $taxClass = new TaxClass();
//                $taxClass->setId($i);
//                $children = $element->childNodes;
//                /** @var $child DOMNode */
//                foreach ($children as $child) {
//                    if (isset($child->nodeName) && (in_array($child->nodeName, self::$translations))) {
//                        $translation = new TaxClassTranslation();
//                        $translation->setLocale($child->nodeName);
//                        $translation->setName($child->nodeValue);
//                        $translation->setTaxClass($taxClass);
//                        $manager->persist($translation);
//                    }
//                }
//                $manager->persist($taxClass);
//                $i++;
//            }
//        }
//        $manager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 1;
    }
}
