<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DataFixtures\ORM\CountryTaxes;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityNotFoundException;
use Sulu\Bundle\ContactBundle\Entity\Country;
use Sulu\Bundle\ProductBundle\Entity\CountryTax;

class LoadCountryTaxes implements FixtureInterface, OrderedFixtureInterface
{
    private static $taxClassEntityName = 'SuluProductBundle:TaxClass';
    private static $countryEntityName = 'SuluContactBundle:Country';

    /**
     * @var ObjectManager
     */
    private $manager;

    /**
     * Cache for already processed countries.
     *
     * @var array
     */
    private $countries = [];

    /**
     * Adapt these if there are any changes in database.
     *
     * @var array
     */
    private static $taxClassMappings = [
        'standard' => 1,
        'reduced1' => 2,
        'reduced2' => 3,
        'reduced3' => 4,
    ];

    /**
     * {@inheritdoc}
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
                    if (!isset($child->{'tax-class'}) ||
                        !isset($child->country) ||
                        !isset($child->tax)
                    ) {
                        throw new Exception('tax-class ' . $i . ' is incomplete');
                    }

                    $countryTax = new CountryTax();
                    $countryTax->setId($i);

                    // Check if mapping for tax-class exists
                    $taxClassKey = (string) $child->{'tax-class'};
                    if (array_key_exists($taxClassKey, static::$taxClassMappings)) {
                        // Set taxclass as defined in mappings array above
                        $taxClass = $taxClasses[static::$taxClassMappings[$taxClassKey]];
                        $countryTax->setTaxClass($taxClass);
                    } else {
                        throw new Exception('tax-class not defined for element country-tax number ' . $i);
                    }

                    $country = $this->getCountryByCode((string) $child->country);
                    $countryTax->setCountry($country);
                    $countryTax->setTax((float) $child->tax);

                    $manager->persist($countryTax);
                    ++$i;
                } catch (Exception $e) {
                    throw $e;
                }
            }
        }
        $manager->flush();
    }

    /**
     * Finds country by code and caches it.
     *
     * @param string $code
     *
     * @throws EntityNotFoundException
     *
     * @return Country
     */
    private function getCountryByCode($code)
    {
        // Check if country exist in cache.
        if (array_key_exists($code, $this->countries)) {
            return $this->countries[$code];
        }
        // Fetch country.
        $country = $this->manager->getRepository(static::$countryEntityName)->findOneBy(['code' => $code]);
        if (!$country) {
            throw new \Doctrine\ORM\EntityNotFoundException('code = ' . $code);
        }
        // Add country to cache.
        $this->countries[$code] = $country;

        return $country;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder()
    {
        return 2;
    }
}
