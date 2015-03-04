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
    private static $taxClassMappings = array(
        'standard' => 1,
        'reduced1' => 2,
        'reduced2' => 3,
        'reduced3' => 4,
    );

    private static $taxClassEntityName = 'SuluProductBundle:TaxClass';
    private static $countryEntityName = 'SuluContactBundle:Country';

    private $countries = array();
    private $manager;

//    private static $translations = ['de', 'en'];
    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $this->manager = $manager;

        // force id = 1
        $metadata = $manager->getClassMetaData(get_class(new CountryTax()));
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        $taxClassEntities = $manager->getRepository(static::$taxClassEntityName)->findAll();
        foreach ($taxClassEntities as $taxClass) {
            $taxClasses[$taxClass->getId()] = $taxClass;
        }

        $i = 1;
        $file = dirname(__FILE__) . '/../../country-taxes.xml';
        $elements = simplexml_load_file($file);

        if (!is_null($elements)) {
            /** @var $element DOMNode */
            foreach ($elements as $child) {
                try {
                    // check if all necessary parameters are given
                    if (!isset($child->{"tax-class"}) ||
                        !isset($child->country) ||
                        !isset($child->tax)
                    ) {
                        throw new Exception('tax-class ' . $i . ' is incomplete');
                    }

                    $countryTax = new CountryTax();
                    $countryTax->setId($i);

                    // check if mapping for tax-class exists
                    $taxClassKey = (string)$child->{"tax-class"};
                    if (array_key_exists($taxClassKey, static::$taxClassMappings)) {
                        // set taxclass as defined in mappings array above
                        $taxClass = $taxClasses[static::$taxClassMappings[$taxClassKey]];
                        $countryTax->setTaxClass($taxClass);
                    } else {
                        throw new Exception('tax-class not defined for element country-tax number ' . $i);
                    }

                    // set country
                    $country = $this->getCountryByCode((string)$child->country);
                    $countryTax->setCountry($country);

                    // set tax
                    $countryTax->setTax((float)$child->tax);

                    $manager->persist($countryTax);
                    $i++;
                } catch (Exception $e) {
                    throw $e;
                }
            }

        }
        $manager->flush();
    }

    /**
     * finds country by code and caches it
     *
     * @param $code
     * @return mixed
     * @throws \Doctrine\ORM\EntityNotFoundException
     */
    private function getCountryByCode($code)
    {
        if (array_key_exists($code, $this->countries)) {
            return $this->countries[$code];
        }
        $country = $this->manager->getRepository(static::$countryEntityName)->findOneBy(array('code' => $code));
        if (!$country) {
            throw new \Doctrine\ORM\EntityNotFoundException('code = ' . $code);
        }
        $this->countries[$code] = $country;

        return $country;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder()
    {
        return 2;
    }
}
